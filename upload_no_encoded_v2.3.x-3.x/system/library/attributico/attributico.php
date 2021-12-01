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