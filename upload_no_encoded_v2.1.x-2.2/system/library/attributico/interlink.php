<?php

function rus2translit($string)
{
    $converter = array(
        'а' => 'a',   'б' => 'b',   'в' => 'v',
        'г' => 'g',   'д' => 'd',   'е' => 'e',
        'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
        'и' => 'i',   'й' => 'y',   'к' => 'k',
        'л' => 'l',   'м' => 'm',   'н' => 'n',
        'о' => 'o',   'п' => 'p',   'р' => 'r',
        'с' => 's',   'т' => 't',   'у' => 'u',
        'ф' => 'f',   'х' => 'h',   'ц' => 'c',
        'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
        'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
        'э' => 'e',   'ю' => 'yu',  'я' => 'ya',

        'А' => 'A',   'Б' => 'B',   'В' => 'V',
        'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
        'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
        'И' => 'I',   'Й' => 'Y',   'К' => 'K',
        'Л' => 'L',   'М' => 'M',   'Н' => 'N',
        'О' => 'O',   'П' => 'P',   'Р' => 'R',
        'С' => 'S',   'Т' => 'T',   'У' => 'U',
        'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
        'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
        'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
        'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
    );
    return strtr($string, $converter);
}

function str2url($str)
{
    // переводим в транслит
    $str = rus2translit($str);
    // в нижний регистр
    $str = strtolower($str);
    // заменям все ненужное нам на "-"
    $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
    // удаляем начальные и конечные '-'
    $str = trim($str, "-");
    return $str;
}

class Interlink
{
    /**
     * Incoming variables
     *
     * @var string
     */
    private $product_id;
    private $attribute_id;
    private $name;
    /**
     * Array of categories id
     *
     * @var array
     */
    private $categories;
    private $main_category;

    private $filters;
    private $value;
    private $url;
    /**
     * Profile of filter 
     * Included variable in args
     *
     * @var array
     */
    private $profile;
    private $path;
    private $alias;

    public function __construct(array $data = [], array $profile = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        $this->profile = $profile;
        // $this->alias = isset($profile['filter_alias']) ? $profile['filter_alias'] : '';
    }

    public function setUrl(Url $url)
    {
        $admindUrl =  $url->link($this->profile['route'], $this->profile['args']);
        $clearurl = str_replace('admin/', '', $admindUrl);
        $this->url =  html_entity_decode($clearurl);
    }

    private function parse()
    {
        $parsedUrl = $this->url;

        /* If URL includes template */
        $pattern = "|{(.+?)}|im";
        $matches = array();
        preg_match_all($pattern, $parsedUrl, $matches);
        if ($matches[1]) {
            foreach ($matches[1] as $match) {
                /* If Match is valid */
                /*  if (property_exists($this, $match)) { */
                if (isset($this->{$match})) {
                    $parsedUrl = str_replace('{' . $match . '}', $this->replaceRule($match), $parsedUrl);
                }
            }
        }

        return html_entity_decode($parsedUrl);
    }
    //TODO decorator
    public function create()
    {
        return $this->parse();
    }

    /**
     * Checking existing method for variable match and return it's worked result
     *
     * @param string $match
     * @return string
     */
    private function replaceRule($match)
    {
        if (method_exists($this, $match . "Rule")) {
            return $this->{$match . "Rule"}();
        } elseif (isset($this->{$match})) {
            return $this->{$match}->replace();
        } else {
            /* No rule for this match */
            return '{' . $match . '}';
        }
    }

    /* protected function aliasRule()
    {
        return $this->alias;
    } */
}

interface ReplaceRuleInterface
{
    public function replace();
}

abstract class ReplaceRule implements ReplaceRuleInterface
{
    protected $parameter;
    protected $profile;

    public function __construct($parameter, array $profile = [])
    {
        $this->parameter = $parameter;
        $this->profile = $profile;
    }

    public function replace()
    {
        return $this->parameter;
    }
}

class ReplacePath extends ReplaceRule
{
    private $main_category;

    function __construct($parameter, array $profile = [], $main_category = '0') {
        parent::__construct($parameter, $profile);
        $this->main_category = $main_category;
    }

    public function setMainCategory($main_category)
    {
        $this->main_category = $main_category;
    }

    public function replace()
    {       
        $main_path = [];
        foreach ($this->parameter as $chain) {
            if (isset($this->main_category)) {
                if ($chain['category_id_1'] === $this->main_category) {
                    $main_path = $chain;
                }
            }
        }

        $hightly_likely_chain = array_reduce($this->parameter, function ($carry, $item) {
            if (strpos($item['path'], $carry['path']) !== false && strlen($item['path']) > strlen($carry['path'])) {
                return $item;
            } else {
                return $carry;
            }
        }, $main_path);

        return 'path=' . $hightly_likely_chain['path'];
    }
}

class ReplaceValue extends ReplaceRule
{
    private $splitter = '/';

    function __construct($parameter, array $profile = [], $splitter = '/') {
        parent::__construct($parameter, $profile);
        $this->splitter = $splitter;
    }

    public function setSplitter($splitter)
    {
        $this->splitter = $splitter;
    }

    public function replace()
    {
        $values = splitValue($this->parameter, $this->splitter);
        $valuesUrl = implode($this->profile['value_separator'] ? $this->profile['value_separator'] : ',', array_map('str2url', $values));
        return $valuesUrl;
    }
}

class ReplaceFilters extends ReplaceRule
{
    public function replace()
    {
        $value_separator = $this->profile['value_separator'] ?: ',';
        $filtersUrl = implode($value_separator, array_map(function ($f) {
            return $f['filter_id'];
        }, $this->parameter));
        return $filtersUrl;
    }
}

class ReplaceProductID extends ReplaceRule
{
}

class ReplaceAttributeID extends ReplaceRule
{
}

class ReplaceName extends ReplaceRule
{
    public function replace()
    {
        return str2url($this->parameter);
    }
}

class ReplaceMainCategory extends ReplaceRule
{
}

class ReplaceCategories extends ReplaceRule
{
    public function replace()
    {
        return implode($this->profile['value_separator'], $this->parameter);
    }
}

class ReplaceAlias extends ReplaceRule
{
}
