<?php
// Planted issues (see evals/evals.json): PHP 4 constructor, dynamic property, curly-brace offset,
// ${} interpolation, each(), create_function(), implicitly nullable parameter.

class OldStyle
{
    var $name;

    function OldStyle($name)
    {
        $this->name = $name;
        $this->upper = strtoupper($name);
    }

    function first()
    {
        return $this->name{0};
    }

    function greet()
    {
        $name = $this->name;
        return "Hello ${name}";
    }

    function pairs(array $list)
    {
        $out = array();
        reset($list);
        while (list($key, $value) = each($list)) {
            $out[] = $key . '=' . $value;
        }
        return implode(',', $out);
    }

    function sortWords(array $words)
    {
        usort($words, create_function('$a, $b', 'return strcmp($a, $b);'));
        return $words;
    }

    function label(array $options = null)
    {
        if ($options === null) {
            return $this->upper;
        }
        return $this->upper . ':' . implode('|', $options);
    }
}
