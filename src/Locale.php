<?php

namespace NikolaDev\ImagineCode;

class Locale {
    protected static $initialized = false;

    /**
     * Currently active language
     * @var null
     */
    protected static $language = null;

    /**
     * Currently active language file
     * @var null
     */
    static $file = null;
    /**
     * Directory where language files are (or will be) located
     * @var string
     */
    static $language_dir;

    /**
     * Array of all strings loaded from language file
     * @var array
     */
    static $strings = array();

    /**
     * Initialize language files.
     *
     * @throws \Exception
     */
    static function init($language_dir, $language) {
        if(!static::$initialized || $language != static::$language) {
            static::$initialized = false;
            $dir = Utils::strip_last_slash($language_dir);
            if(!is_dir($dir) && !mkdir($dir, 0755, true))
                throw new \Exception(sprintf("Language directory %s is missing or cannot be accessed.", $dir));

            static::$language_dir = $dir;
            $file = static::get_file_path($language);
            if(!file_exists($file))
                throw new \Exception(sprintf("Language file \"%s\" is missing or cannot be accessed.", $file));

            static::$file = $file;
            static::$language = $language;

            static::load();

            static::$initialized = true;
        }
    }

    /**
     * Get current locale
     * @return null
     */
    static function get_locale() {
        return static::$language;
    }
    /** Actions */
    /**
     * Return translated string if found, otherwise $str will be returned.
     *
     * @param string $str
     * @param mixed ...$values - additional arguments used for sprintf
     * @return string
     */
    static function get($str, ...$values) {
        $args = func_get_args();
        $str = array_shift($args);
        if(array_key_exists($str, static::$strings) && !empty(static::$strings[$str]))
            return call_user_func_array('sprintf', array_merge([static::$strings[$str]], $args));

        return $str;
    }

    /**
     * Echo translated string.
     *
     * @uses get
     *
     * @param string $str
     */
    static function out($str) {
        echo static::get($str);
    }

    /**
     * Load strings from file.
     *
     * @return bool
     * @throws \Exception
     */
    protected static function load() {
        $strings = static::parse_language_file(static::$file);

        if($strings === false)
            throw new \Exception(sprintf("Language file \"%s\" could not be loaded.", basename(static::$file)));

        static::$strings = $strings;

        return true;
    }
    /**
     * Parse language file into array of strings.
     * Returns FALSE if file doesn't exist, is empty or no strings were found, or could not be parsed for any other reason.
     *
     * @param $file
     * @return array|bool
     */
    private static function parse_language_file($file) {
        if(!file_exists($file))
            return false;

        $ids = array();
        $texts = array();
        $content = file_get_contents($file);

        if(empty($content))
            return array();

        $id_dq = '/msgid(?:\s*)"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s';
        $txt_dq = '/msgstr(?:\s*)"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s';
        if (preg_match_all($id_dq, $content, $matches)) {
            $ids = $matches[0];
        }
        unset($matches);
        if (preg_match_all($txt_dq, $content, $matches)) {
            $texts = $matches[0];
        }
        $strings = array();
        for($i = 0; $i < count($ids); $i++) {
            $id = $ids[$i];
            $str = @$texts[$i];

            $id = str_replace('msgid', '', $id);
            $str = str_replace('msgstr', '', $str);

            $id = trim($id);
            $str = trim($str);

            $id = substr($id, 1, strlen($id) - 2);
            $str = substr($str, 1, strlen($str) - 2);

            $strings[stripslashes($id)] = stripslashes($str);
        }

        if(empty($strings))
            return false;

        return $strings;
    }
    /**
     * Get file path based on $language code.
     *
     * @param $language
     * @return string
     */
    private static function get_file_path($language) {
        return static::$language_dir . "/" . $language . ".locale";
    }
    /**
     * Check if string has UTF-8 encoding.
     *
     * @param $string
     *
     * @return bool
     */
    private static function is_utf8($string) {
        return (mb_detect_encoding($string, 'UTF-8', true) == 'UTF-8');
    }
}