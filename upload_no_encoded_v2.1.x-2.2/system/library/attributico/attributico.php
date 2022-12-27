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
    function mb_ucfirst($string)
    {
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

if (!function_exists('mb_lcfirst') && function_exists('mb_substr')) {
    function mb_lcfirst($string)
    {
        $string = mb_strtolower(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

/**
 * All templates of this attribute will be separated using a splitter and transferred to a plane array of values
 *
 * @param array $templates
 * @return array
 */
function splitTemplate($templates, $splitter)
{
    $all_elements = array();
    foreach ($templates as $template) {
        $elements = explode($splitter, $template['text']);

        foreach ($elements as $element) {
            if ($element != "") {
                $all_elements[] = trim($element);
            }
        }
    }
    $value_list = array_unique($all_elements);
    array_multisort($value_list);

    return $value_list;
}

/**
 * Only one template string will be separated using a splitter and transferred to a plane array of values
 *
 * @param string $value
 * @return array
 */
function splitValue($value, $splitter)
{
    $all_elements = array();
    $elements = explode($splitter, $value);

    foreach ($elements as $element) {
        if ($element != "") {
            $all_elements[] = trim($element);
        }
    }

    $value_list = array_unique($all_elements);
    array_multisort($value_list);

    return $value_list;
}

function compareValue($value, $template, $value_compare_mode = 'substr', $splitter)
{
    $value_list = splitValue($template, $splitter);
    if ($value_compare_mode === 'substr') {
        return (strpos($template, $value) !== false);
    }
    if ($value_compare_mode === 'match') {
        return in_array(strtolower($value), array_map('strtolower', $value_list));
    }
    return false;
}

function replaceValue($old_value, $new_value, $template, $value_compare_mode, $splitter)
{
    // Stupid PHP! Preg_replace has stripped slash in search.
    $search = preg_quote(htmlspecialchars_decode($old_value, ENT_QUOTES), '/');
    // Stupid PHP! Preg_replace has stripped backslash in replasement.
    $replace = str_replace('\\', '\\\\', htmlspecialchars_decode($new_value, ENT_QUOTES));
    $template = htmlspecialchars_decode($template, ENT_QUOTES);

    if ($value_compare_mode === 'match') {
        // Замена по точному совпадению значения
        $haystack = explode($splitter, $template);
        $replaced = preg_replace('/^(' . $search . ')+$/', $replace, $haystack);
        $newtext =  implode($splitter, $replaced);
    } else {
        // Замена по вхождению подстроки в строку
        $newtext = str_replace($search, $replace, $template);
    }

    return $newtext;
}

function array_delete_col(&$array, $key)
{
    return array_walk($array, function (&$v) use ($key) {
        unset($v[$key]);
    });
}

function array_columns($array, $columns_wanted)
{
    $filtered_array = [];

    foreach ($array as $sub_array) {
        $filtered_array[] = array_intersect_key($sub_array, array_fill_keys($columns_wanted, ''));
    }
    return  $filtered_array;
}

/**
 * Group data by languages     * 
 * Needs row['language_id']
 * 
 * @param array $rows
 * @return array
 */
function groupByLang($rows)
{
    $result = [];
    foreach ($rows as $row) {
        $lang_data = [];
        foreach ($row as $key => $value) {
            $lang_data[$key] = $value;
        }
        $result[$row['language_id']] = $lang_data;
    }
    return $result;
}

function typeChecking($set)
{
    /* $array_iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($set), RecursiveIteratorIterator::CATCH_GET_CHILD);
    foreach ($array_iterator as $key => $value) {
        if (is_numeric($value)) {
            $set[$key] = (int)$value;
        } else if (is_string($value)) {
            $set[$key] = htmlspecialchars_decode($value, ENT_QUOTES);
        }
    } */    
    foreach ($set as $key => $element) {
        if (is_array($element)) {
            foreach ($element as $subkey => $value) {
                if (is_numeric($value)) {
                    $set[$key][$subkey] = (int)$value;
                } else if (is_string($value)) {
                    $set[$key][$subkey] = htmlspecialchars_decode($value, ENT_QUOTES);
                }
            }
        } else {
            if (is_numeric($element)) {
                $set[$key] = (int)$element;
            } else if (is_string($element)) {
                $set[$key] = htmlspecialchars_decode($element, ENT_QUOTES);
            }
        }
    }
   
    return $set;
}
