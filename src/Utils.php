<?php

namespace NikolaDev\ImagineCode;

class Utils {

    /**
     * Check if $haystack starts with $needle.
     *
     * @param string $haystack
     * @param string|array $needle
     * @param bool $case_sensitive
     * @return bool
     */
    static function starts_with($haystack, $needle, $case_sensitive = false) {
        if(!is_array($needle))
            $needle = array($needle);
        foreach($needle as $n) {
            if(($case_sensitive ? strpos($haystack, $n) : stripos($haystack, $n)) === 0)
                return true;
        }
        return false;
    }
    /**
     * Check if string ends with another string.
     *
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    static function ends_with($haystack, $needle) {
        return substr($haystack, strlen($haystack) - strlen($needle), strlen($needle)) === $needle;
    }

    /**
     * Remove all "/" characters at the end of string.
     *
     * @param $value
     *
     * @return string
     */
    static function strip_last_slash($value) {
        while(static::ends_with($value, '/'))
            $value = substr($value, 0, strlen($value) - 1);

        return $value;
    }

    /**
     * List files in directories recursively.
     *
     * @param     $pattern
     * @param int $flags
     *
     * @return array
     */
    static function rglob($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, static::rglob($dir . '/' . basename($pattern), $flags));
        }
        return $files;
    }

    /**
     * Checks if given array is associative array (array of key=>value pairs).
     *
     * @param array $array
     * @return bool
     */
    static function is_assoc($array) {
        if (array() === $array)
            return false;

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Prepend one or more elements to the beginning of an associative array.
     *
     * @param $arr
     * @param $key
     * @param $val
     * @return array
     */
    static function array_unshift_assoc(&$arr, $key, $val) {
        $arr = array_reverse($arr, true);
        $arr[$key] = $val;
        $arr = array_reverse($arr, true);
        return $arr;
    }

    /**
     * Check if script is accessed from console.
     *
     * @return bool
     */
    static function is_cli() {
        return php_sapi_name() == "cli";
    }

    /**
     * Check client's IP (compatible with CloudFlare)
     * @param $ip
     * @return bool
     */
    static function is_ip($ip) {
        if(!is_array($ip))
            $ip = array($ip);

        $c = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : @$_SERVER['REMOTE_ADDR'];

        if(!$c)
            return true;

        foreach($ip as $i) {
            if($c == $i)
                return true;
        }

        return false;
    }
}