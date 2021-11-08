<?php
class Tree
{

    private $options = array();
    private $rootNode = array();   

    public function __construct(Node $node, $options = array())
    {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }
        $this->rootNode[0] = $node->nodeData;
    }

    public function set($key, $value)
    {
        $this->options[$key] = $value;
    }

    public function render()
    {
        return $this->rootNode;
    }

    public function renderjson()
    {
        return json_encode($this->rootNode);
    }
}
class Node
{

    private $nodeData = array();

    public function __construct($options = array())
    {
        foreach ($options as $key => $value) {
            $this->nodeData[0][$key] = $value;
        }
    }

    public function __get($property)
    {
        if ($property == "nodeData") {
            if ($this->nodeData) {
                return $this->nodeData[0];
            } else {
                return array();
            }
        } else {
            foreach ($this->nodeData[0] as $data) {
                if (array_key_exists($property, $data)) {
                    $aprop[] = $data[$property];
                } else {
                    $aprop[] = null;
                }
            }
            return $aprop;
        }
    }

    public function __set($property, $value)
    {
        if ($property == "nodeData") {
            $this->nodeData = array();
            $this->nodeData[0] = $value;
        } else {
            foreach ($this->nodeData[0] as $data) {
                if (array_key_exists($property, $data)) {
                    $data[$property] = $value;
                }
            }
        }
    }

    public function addSibling(Node $sibling)
    {
        $this->nodeData[] = $sibling->nodeData[0];
    }

    public function sort()
    {
        $sort_order = array();
        foreach ($this->nodeData as $key => $value) {
            $sort_order[$key] = $value['title'];
        }
        array_multisort($sort_order, SORT_ASC, $this->nodeData);
    }

    public function render()
    {
        return $this->nodeData;
    }
}

if (!function_exists('mb_ucfirst') && function_exists('mb_substr')) {
    function mb_ucfirst($string) {
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

if (!function_exists('mb_lcfirst') && function_exists('mb_substr')) {
    function mb_lcfirst($string) {
        $string = mb_strtolower(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

function rus2translit($string) {
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

function str2url($str) {
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