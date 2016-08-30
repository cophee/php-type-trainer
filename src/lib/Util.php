<?php

namespace mpyw\PhpTypeTrainer\lib;

final class Util
{
    private function __construct()
    {
    }

    public static function write($str = '')
    {
        echo "$str";
    }

    public static function writeln($str = '')
    {
        echo "$str\n";
    }

    public static function errorln($str = '')
    {
        fprintf(STDERR, "%s\n", $str);
    }

    public static function error($str = '')
    {
        fprintf(STDERR, "%s", $str);
    }

    public static function prompt($msg = '')
    {
        self::write($msg);
        return trim(fgets(STDIN));
    }

    public static function promptYN($msg = '')
    {
        while (true) {
            $answer = strtoupper(self::prompt($msg));
            if ($answer === 'Y') {
                return true;
            } elseif ($answer === 'N') {
                return false;
            }
        }
    }

    public static function promptNumber($msg = '', $min, $max)
    {
        while (true) {
            $answer = filter_var(self::prompt($msg), FILTER_VALIDATE_INT, array(
                'options' => array(
                    'min_range' => $min,
                    'max_range' => $max,
                ),
            ));
            if ($answer !== false) {
                return $answer;
            }
        }
    }

    public static function intersect(array $src, array $keys)
    {
        return array_intersect_key($src, array_flip($keys));
    }

    public static function appendWhiteSpaces(array $strings)
    {
        $max = max(array_map('strlen', $strings));
        foreach ($strings as &$str) {
            $str .= str_repeat(' ', $max - strlen($str));
        }
        return $strings;
    }
}
