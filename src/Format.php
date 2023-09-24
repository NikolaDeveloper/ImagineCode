<?php

namespace NikolaDev\ImagineCode;

class Format {

    static function hash_password($plain_password, $salt = '') {
        return Events::filter('hash_password', hash('sha512', $plain_password . $salt));
    }

    static function filter_output(&$array) {
        array_walk_recursive($array, function(&$value) {
            if(is_float($value) || is_numeric($value) || is_bool($value))
                return;

            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        });
    }

    static function price($num, $decimals = 2, $separator = ',') {
        if($num < 0){
            $num = abs($num);
            $result = '-' . number_format($num, $decimals, '.', $separator);
        }else {
            $result = number_format((float)$num, $decimals, '.', $separator);
        }
        return $result;
    }
    static function eu_price($num, $decimals = 2, $separator = '.') {
        if($num < 0){
            $num = abs($num);
            $result = '-' . number_format($num, $decimals, ',', $separator);
        }else {
            $result = number_format($num, $decimals, ',', $separator);
        }
        return $result;
    }

    static function maybe_unserialize($var) {
        $v = @unserialize($var);
        if ($var !== 'b:0;' && $v === false)
            $v = $var;

        return $v;
    }
    /**
     * Format string into HTML attribute-friendly format by replacing special characters
     * with HTML entities.
     *
     * @param $str
     * @param int $flags
     * @param string $encoding
     * @param bool $double_encode
     *
     * @return string
     */
    static function esc_attr($str, $flags = ENT_COMPAT, $encoding = 'UTF-8', $double_encode = true) {
        return htmlspecialchars($str, $flags, $encoding, $double_encode);
    }

    static function esc_html($str, $flags = ENT_COMPAT, $encoding = 'UTF-8', $double_encode = true) {
        return htmlspecialchars($str, $flags, $encoding, $double_encode);
    }
    /**
     * Returns original url with added GET params
     *
     * @param $url
     * @param array $params
     * @param bool $overwrite - if true, existing URL params will be overwritten with new ones, otherwise new duplicates will be ignored
     * @return string
     */
    static function url($url, $params = array(), $overwrite = false) {
        if(empty($url))
            return $url;

        $base_url = defined('BASE_URL') ? BASE_URL : '';
        if(Utils::starts_with($url, '/'))
            $url = rtrim($base_url, '/') . '/' . ltrim($url, '/');

        $existing = static::parse_url_params($url);

        $url = static::strip_url_params($url);
        $query = '';

        foreach($params as $k=>$v) {
            if(array_key_exists($k, $existing)) {
                if($overwrite)
                    unset($existing[$k]);
                else
                    unset($params[$k]);
            }
            if(is_array($v)) {
                $params[$k] = array_unique($v);
                sort($params[$k]);
            }
        }
        $params = array_merge($params, $existing);
        foreach($params as $k=>$v) {
            if(!empty($query))
                $query .= "&";

            if(is_array($v)) {
                for($i = 0; $i < count($v); $i++) {
                    $query .= $k . "[]=" . rawurlencode($v[$i]);

                    if($i < count($v) - 1)
                        $query .= "&";
                }
            }
            else
                $query .= "$k=" . rawurlencode($v);
        }

        $url = rtrim($url, '/');
        if(!empty($params)) {
            $url .= (strpos($url, '?') === false) ? "?" : "&";
            $url .= $query;
        }
        $url = rtrim($url, '/');

        return $url;
    }
    static function get_current_url() {
        return (isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    }
    static function url_params_match($url, $second_url = null) {
        if($second_url == '')
            $second_url = static::get_current_url();

        $p1 = static::parse_url_params($url);
        $p2 = static::parse_url_params($second_url);

        foreach($p1 as $k=>$v) {
            if(!array_key_exists($k, $p2) || $p2[$k] != $v)
                return false;
        }

        return true;
    }
    static function get_url_param($key, $url = null, $default = null) {
        if($url == '')
            return g($key, $default);

        $params = static::parse_url_params($url);
        if(array_key_exists($key, $params))
            return $params[$key];

        return $default;
    }
    static function parse_url_params($url) {
        $params = array();
        $data = parse_url($url);
        if(isset($data['query']) && !empty($data['query'])) {
            $tmp = explode("&", $data['query']);

            foreach($tmp as $query) {
                $tmp2 = explode("=", $query);
                $key = $tmp2[0];
                $value = substr($query,strlen($key."="), strlen($query) - strlen($key."="));
                if(isset($params[$key])) {
                    if(!is_array($params[$key]))
                        $params[$key] = array($params[$key]);

                    $params[$key][] = $value;
                }
                else
                    $params[$key] = $value;
            }
        }
        return $params;
    }
    static function strip_url_params($url) {
        $url = explode("?", $url);

        return $url[0];
    }
    static function unset_url_params($url, $params = array()) {
        $existing = static::parse_url_params($url);
        $url = static::strip_url_params($url);
        foreach($params as $p)
            unset($existing[$p]);

        return static::url($url, $existing);
    }
    static function str_url_friendly($value, $space = '-') {
        $value = strtolower($value);
        $value = trim(str_replace(' ', $space, $value));
        $value = preg_replace("/[^a-zA-Z0-9\-\_]/", "", $value);
        $value = trim(preg_replace("/\\s+/", " ", $value));
        $value = str_replace(" ", $space, $value);

        return $value;
    }
    static function str_shorten($str, $len, $trail = '') {
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        if (strlen($str) <= $len)
            return $str;

        return trim(substr($str, 0, $len - strlen($trail))) . $trail;
    }

    static function str_random($length, $allow_special = true, $special_characters = null) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($allow_special) {
            if ($special_characters == '')
                $characters .= '!@#$%^&*()_-.,';
            else
                $characters .= $special_characters;
        }

        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
     /**
     * Returns formatted number.
     *
     * @param $num
     * @param int $decimals
     *
     * @return string
     */
    static function num($num, $decimals = 0) {
        if($num < 0){
            $num = abs($num);
            $result = '-' . number_format($num, $decimals, '.', ',');
        }else {
            $result = number_format($num, $decimals, '.', ',');
        }
        return $result;
    }
    static function strip_http($url) {
        $url = trim($url);

        if(substr($url, 0, 6) == 'https:')
            $url = substr($url, 6);
        elseif(substr($url, 0, 5) == 'http:')
            $url = substr($url, 5);

        return $url;
    }

    static function time_ago($seconds, $no_seconds = false) {
        return static::format_seconds($seconds, $no_seconds);
    }
    static function format_seconds($seconds, $no_seconds = false) {
        $sec_num = (int)$seconds;
        $hours   = floor($sec_num / 3600);
        $minutes = floor(($sec_num - ($hours * 3600)) / 60);
        $seconds = $sec_num - ($hours * 3600) - ($minutes * 60);

        if($hours < 1) {
            if($minutes < 1)
                return $no_seconds ? '1m' : $seconds . 's';

            return $minutes . 'm' . ($seconds && !$no_seconds ? ' ' . $seconds . 's' : '');
        }
        return $hours . 'h' . ($minutes ? ' ' . $minutes . 'm' : '') . (!$no_seconds && $seconds && $minutes ? ' ' . $seconds . 's' : '');
    }
    static function format_bytes($bytes, $precision = 2, $byte_per_kb=1024) {
        $units = array('KB', 'MB', 'GB', 'TB', 'PB');

        $result = $bytes > 0 ? $bytes : 0;
        if ($result <= $byte_per_kb)
            return $result . ' B';

        for ($x = 0; $x < count($units); $x++) {
            for($z = 0; $z < $x + 1; $z++)
                $result /= $byte_per_kb;
            $r = round($result, $precision);
            if ($r <= $byte_per_kb)
                return sprintf('%.2f %s', $r, $units[$x]);
            else
                $result = $bytes;
        }
        return $result;
    }
    static function mbstring_binary_safe_encoding( $reset = false ) {
        static $encodings  = array();
        static $overloaded = null;

        if ( is_null( $overloaded ) ) {
            $overloaded = function_exists( 'mb_internal_encoding' ) && ( ini_get( 'mbstring.func_overload' ) & 2 ); // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.mbstring_func_overloadDeprecated
        }

        if ( false === $overloaded ) {
            return;
        }

        if ( ! $reset ) {
            $encoding = mb_internal_encoding();
            array_push( $encodings, $encoding );
            mb_internal_encoding( 'ISO-8859-1' );
        }

        if ( $reset && $encodings ) {
            $encoding = array_pop( $encodings );
            mb_internal_encoding( $encoding );
        }
    }
    static function reset_mbstring_encoding() {
        static::mbstring_binary_safe_encoding(true);
    }
    static function seems_utf8( $str ) {
        static::mbstring_binary_safe_encoding();
        $length = strlen( $str );
        static::reset_mbstring_encoding();
        for ( $i = 0; $i < $length; $i++ ) {
            $c = ord( $str[ $i ] );
            if ( $c < 0x80 ) {
                $n = 0; // 0bbbbbbb
            } elseif ( ( $c & 0xE0 ) == 0xC0 ) {
                $n = 1; // 110bbbbb
            } elseif ( ( $c & 0xF0 ) == 0xE0 ) {
                $n = 2; // 1110bbbb
            } elseif ( ( $c & 0xF8 ) == 0xF0 ) {
                $n = 3; // 11110bbb
            } elseif ( ( $c & 0xFC ) == 0xF8 ) {
                $n = 4; // 111110bb
            } elseif ( ( $c & 0xFE ) == 0xFC ) {
                $n = 5; // 1111110b
            } else {
                return false; // Does not match any model.
            }
            for ( $j = 0; $j < $n; $j++ ) { // n bytes matching 10bbbbbb follow ?
                if ( ( ++$i == $length ) || ( ( ord( $str[ $i ] ) & 0xC0 ) != 0x80 ) ) {
                    return false;
                }
            }
        }
        return true;
    }
    static function remove_accents( $string ) {
        if ( ! preg_match( '/[\x80-\xff]/', $string ) ) {
            return $string;
        }

        if ( static::seems_utf8( $string ) ) {
            $chars = array(
                // Decompositions for Latin-1 Supplement.
                'ª' => 'a',
                'º' => 'o',
                'À' => 'A',
                'Á' => 'A',
                'Â' => 'A',
                'Ã' => 'A',
                'Ä' => 'A',
                'Å' => 'A',
                'Æ' => 'AE',
                'Ç' => 'C',
                'È' => 'E',
                'É' => 'E',
                'Ê' => 'E',
                'Ë' => 'E',
                'Ì' => 'I',
                'Í' => 'I',
                'Î' => 'I',
                'Ï' => 'I',
                'Ð' => 'D',
                'Ñ' => 'N',
                'Ò' => 'O',
                'Ó' => 'O',
                'Ô' => 'O',
                'Õ' => 'O',
                'Ö' => 'O',
                'Ù' => 'U',
                'Ú' => 'U',
                'Û' => 'U',
                'Ü' => 'U',
                'Ý' => 'Y',
                'Þ' => 'TH',
                'ß' => 's',
                'à' => 'a',
                'á' => 'a',
                'â' => 'a',
                'ã' => 'a',
                'ä' => 'a',
                'å' => 'a',
                'æ' => 'ae',
                'ç' => 'c',
                'è' => 'e',
                'é' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'ì' => 'i',
                'í' => 'i',
                'î' => 'i',
                'ï' => 'i',
                'ð' => 'd',
                'ñ' => 'n',
                'ò' => 'o',
                'ó' => 'o',
                'ô' => 'o',
                'õ' => 'o',
                'ö' => 'o',
                'ø' => 'o',
                'ù' => 'u',
                'ú' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'ý' => 'y',
                'þ' => 'th',
                'ÿ' => 'y',
                'Ø' => 'O',
                // Decompositions for Latin Extended-A.
                'Ā' => 'A',
                'ā' => 'a',
                'Ă' => 'A',
                'ă' => 'a',
                'Ą' => 'A',
                'ą' => 'a',
                'Ć' => 'C',
                'ć' => 'c',
                'Ĉ' => 'C',
                'ĉ' => 'c',
                'Ċ' => 'C',
                'ċ' => 'c',
                'Č' => 'C',
                'č' => 'c',
                'Ď' => 'D',
                'ď' => 'd',
                'Đ' => 'D',
                'đ' => 'd',
                'Ē' => 'E',
                'ē' => 'e',
                'Ĕ' => 'E',
                'ĕ' => 'e',
                'Ė' => 'E',
                'ė' => 'e',
                'Ę' => 'E',
                'ę' => 'e',
                'Ě' => 'E',
                'ě' => 'e',
                'Ĝ' => 'G',
                'ĝ' => 'g',
                'Ğ' => 'G',
                'ğ' => 'g',
                'Ġ' => 'G',
                'ġ' => 'g',
                'Ģ' => 'G',
                'ģ' => 'g',
                'Ĥ' => 'H',
                'ĥ' => 'h',
                'Ħ' => 'H',
                'ħ' => 'h',
                'Ĩ' => 'I',
                'ĩ' => 'i',
                'Ī' => 'I',
                'ī' => 'i',
                'Ĭ' => 'I',
                'ĭ' => 'i',
                'Į' => 'I',
                'į' => 'i',
                'İ' => 'I',
                'ı' => 'i',
                'Ĳ' => 'IJ',
                'ĳ' => 'ij',
                'Ĵ' => 'J',
                'ĵ' => 'j',
                'Ķ' => 'K',
                'ķ' => 'k',
                'ĸ' => 'k',
                'Ĺ' => 'L',
                'ĺ' => 'l',
                'Ļ' => 'L',
                'ļ' => 'l',
                'Ľ' => 'L',
                'ľ' => 'l',
                'Ŀ' => 'L',
                'ŀ' => 'l',
                'Ł' => 'L',
                'ł' => 'l',
                'Ń' => 'N',
                'ń' => 'n',
                'Ņ' => 'N',
                'ņ' => 'n',
                'Ň' => 'N',
                'ň' => 'n',
                'ŉ' => 'n',
                'Ŋ' => 'N',
                'ŋ' => 'n',
                'Ō' => 'O',
                'ō' => 'o',
                'Ŏ' => 'O',
                'ŏ' => 'o',
                'Ő' => 'O',
                'ő' => 'o',
                'Œ' => 'OE',
                'œ' => 'oe',
                'Ŕ' => 'R',
                'ŕ' => 'r',
                'Ŗ' => 'R',
                'ŗ' => 'r',
                'Ř' => 'R',
                'ř' => 'r',
                'Ś' => 'S',
                'ś' => 's',
                'Ŝ' => 'S',
                'ŝ' => 's',
                'Ş' => 'S',
                'ş' => 's',
                'Š' => 'S',
                'š' => 's',
                'Ţ' => 'T',
                'ţ' => 't',
                'Ť' => 'T',
                'ť' => 't',
                'Ŧ' => 'T',
                'ŧ' => 't',
                'Ũ' => 'U',
                'ũ' => 'u',
                'Ū' => 'U',
                'ū' => 'u',
                'Ŭ' => 'U',
                'ŭ' => 'u',
                'Ů' => 'U',
                'ů' => 'u',
                'Ű' => 'U',
                'ű' => 'u',
                'Ų' => 'U',
                'ų' => 'u',
                'Ŵ' => 'W',
                'ŵ' => 'w',
                'Ŷ' => 'Y',
                'ŷ' => 'y',
                'Ÿ' => 'Y',
                'Ź' => 'Z',
                'ź' => 'z',
                'Ż' => 'Z',
                'ż' => 'z',
                'Ž' => 'Z',
                'ž' => 'z',
                'ſ' => 's',
                // Decompositions for Latin Extended-B.
                'Ș' => 'S',
                'ș' => 's',
                'Ț' => 'T',
                'ț' => 't',
                // Euro sign.
                '€' => 'E',
                // GBP (Pound) sign.
                '£' => '',
                // Vowels with diacritic (Vietnamese).
                // Unmarked.
                'Ơ' => 'O',
                'ơ' => 'o',
                'Ư' => 'U',
                'ư' => 'u',
                // Grave accent.
                'Ầ' => 'A',
                'ầ' => 'a',
                'Ằ' => 'A',
                'ằ' => 'a',
                'Ề' => 'E',
                'ề' => 'e',
                'Ồ' => 'O',
                'ồ' => 'o',
                'Ờ' => 'O',
                'ờ' => 'o',
                'Ừ' => 'U',
                'ừ' => 'u',
                'Ỳ' => 'Y',
                'ỳ' => 'y',
                // Hook.
                'Ả' => 'A',
                'ả' => 'a',
                'Ẩ' => 'A',
                'ẩ' => 'a',
                'Ẳ' => 'A',
                'ẳ' => 'a',
                'Ẻ' => 'E',
                'ẻ' => 'e',
                'Ể' => 'E',
                'ể' => 'e',
                'Ỉ' => 'I',
                'ỉ' => 'i',
                'Ỏ' => 'O',
                'ỏ' => 'o',
                'Ổ' => 'O',
                'ổ' => 'o',
                'Ở' => 'O',
                'ở' => 'o',
                'Ủ' => 'U',
                'ủ' => 'u',
                'Ử' => 'U',
                'ử' => 'u',
                'Ỷ' => 'Y',
                'ỷ' => 'y',
                // Tilde.
                'Ẫ' => 'A',
                'ẫ' => 'a',
                'Ẵ' => 'A',
                'ẵ' => 'a',
                'Ẽ' => 'E',
                'ẽ' => 'e',
                'Ễ' => 'E',
                'ễ' => 'e',
                'Ỗ' => 'O',
                'ỗ' => 'o',
                'Ỡ' => 'O',
                'ỡ' => 'o',
                'Ữ' => 'U',
                'ữ' => 'u',
                'Ỹ' => 'Y',
                'ỹ' => 'y',
                // Acute accent.
                'Ấ' => 'A',
                'ấ' => 'a',
                'Ắ' => 'A',
                'ắ' => 'a',
                'Ế' => 'E',
                'ế' => 'e',
                'Ố' => 'O',
                'ố' => 'o',
                'Ớ' => 'O',
                'ớ' => 'o',
                'Ứ' => 'U',
                'ứ' => 'u',
                // Dot below.
                'Ạ' => 'A',
                'ạ' => 'a',
                'Ậ' => 'A',
                'ậ' => 'a',
                'Ặ' => 'A',
                'ặ' => 'a',
                'Ẹ' => 'E',
                'ẹ' => 'e',
                'Ệ' => 'E',
                'ệ' => 'e',
                'Ị' => 'I',
                'ị' => 'i',
                'Ọ' => 'O',
                'ọ' => 'o',
                'Ộ' => 'O',
                'ộ' => 'o',
                'Ợ' => 'O',
                'ợ' => 'o',
                'Ụ' => 'U',
                'ụ' => 'u',
                'Ự' => 'U',
                'ự' => 'u',
                'Ỵ' => 'Y',
                'ỵ' => 'y',
                // Vowels with diacritic (Chinese, Hanyu Pinyin).
                'ɑ' => 'a',
                // Macron.
                'Ǖ' => 'U',
                'ǖ' => 'u',
                // Acute accent.
                'Ǘ' => 'U',
                'ǘ' => 'u',
                // Caron.
                'Ǎ' => 'A',
                'ǎ' => 'a',
                'Ǐ' => 'I',
                'ǐ' => 'i',
                'Ǒ' => 'O',
                'ǒ' => 'o',
                'Ǔ' => 'U',
                'ǔ' => 'u',
                'Ǚ' => 'U',
                'ǚ' => 'u',
                // Grave accent.
                'Ǜ' => 'U',
                'ǜ' => 'u',
            );

            // Used for locale-specific rules.
            $locale = Locale::get_locale();

            if ( in_array( $locale, array( 'de_DE', 'de_DE_formal', 'de_CH', 'de_CH_informal' ), true ) ) {
                $chars['Ä'] = 'Ae';
                $chars['ä'] = 'ae';
                $chars['Ö'] = 'Oe';
                $chars['ö'] = 'oe';
                $chars['Ü'] = 'Ue';
                $chars['ü'] = 'ue';
                $chars['ß'] = 'ss';
            } elseif ( 'da_DK' === $locale ) {
                $chars['Æ'] = 'Ae';
                $chars['æ'] = 'ae';
                $chars['Ø'] = 'Oe';
                $chars['ø'] = 'oe';
                $chars['Å'] = 'Aa';
                $chars['å'] = 'aa';
            } elseif ( 'ca' === $locale ) {
                $chars['l·l'] = 'll';
            } elseif ( 'sr_RS' === $locale || 'bs_BA' === $locale ) {
                $chars['Đ'] = 'DJ';
                $chars['đ'] = 'dj';
            }

            $string = strtr( $string, $chars );
        } else {
            $chars = array();
            // Assume ISO-8859-1 if not UTF-8.
            $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
                . "\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
                . "\xc3\xc4\xc5\xc7\xc8\xc9\xca"
                . "\xcb\xcc\xcd\xce\xcf\xd1\xd2"
                . "\xd3\xd4\xd5\xd6\xd8\xd9\xda"
                . "\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
                . "\xe4\xe5\xe7\xe8\xe9\xea\xeb"
                . "\xec\xed\xee\xef\xf1\xf2\xf3"
                . "\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
                . "\xfc\xfd\xff";

            $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

            $string              = strtr( $string, $chars['in'], $chars['out'] );
            $double_chars        = array();
            $double_chars['in']  = array( "\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe" );
            $double_chars['out'] = array( 'OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th' );
            $string              = str_replace( $double_chars['in'], $double_chars['out'], $string );
        }

        return $string;
    }
    function utf8_uri_encode( $utf8_string, $length = 0 ) {
        $unicode        = '';
        $values         = array();
        $num_octets     = 1;
        $unicode_length = 0;

        static::mbstring_binary_safe_encoding();
        $string_length = strlen( $utf8_string );
        static::reset_mbstring_encoding();

        for ( $i = 0; $i < $string_length; $i++ ) {

            $value = ord( $utf8_string[ $i ] );

            if ( $value < 128 ) {
                if ( $length && ( $unicode_length >= $length ) ) {
                    break;
                }
                $unicode .= chr( $value );
                $unicode_length++;
            } else {
                if ( count( $values ) == 0 ) {
                    if ( $value < 224 ) {
                        $num_octets = 2;
                    } elseif ( $value < 240 ) {
                        $num_octets = 3;
                    } else {
                        $num_octets = 4;
                    }
                }

                $values[] = $value;

                if ( $length && ( $unicode_length + ( $num_octets * 3 ) ) > $length ) {
                    break;
                }
                if ( count( $values ) == $num_octets ) {
                    for ( $j = 0; $j < $num_octets; $j++ ) {
                        $unicode .= '%' . dechex( $values[ $j ] );
                    }

                    $unicode_length += $num_octets * 3;

                    $values     = array();
                    $num_octets = 1;
                }
            }
        }

        return $unicode;
    }
    function format_slug($title) {
        $title = strip_tags( $title );
        // Preserve escaped octets.
        $title = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title );
        // Remove percent signs that are not part of an octet.
        $title = str_replace( '%', '', $title );
        // Restore octets.
        $title = preg_replace( '|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title );

        if ( static::seems_utf8( $title ) ) {
            if ( function_exists( 'mb_strtolower' ) ) {
                $title = mb_strtolower( $title, 'UTF-8' );
            }
            $title = static::utf8_uri_encode( $title, 200 );
        }

        $title = strtolower( $title );

        // Kill entities.
        $title = preg_replace( '/&.+?;/', '', $title );
        $title = str_replace( '.', '-', $title );

        $title = preg_replace( '/[^%a-z0-9 _-]/', '', $title );
        $title = preg_replace( '/\s+/', '-', $title );
        $title = preg_replace( '|-+|', '-', $title );
        $title = trim( $title, '-' );

        return $title;
    }
    static function remove_emoji($string) {
        $symbols = "\x{1F100}-\x{1F1FF}" // Enclosed Alphanumeric Supplement
            ."\x{1F300}-\x{1F5FF}" // Miscellaneous Symbols and Pictographs
            ."\x{1F600}-\x{1F64F}" //Emoticons
            ."\x{1F680}-\x{1F6FF}" // Transport And Map Symbols
            ."\x{1F900}-\x{1F9FF}" // Supplemental Symbols and Pictographs
            ."\x{2600}-\x{26FF}" // Miscellaneous Symbols
            ."\x{2700}-\x{27BF}"; // Dingbats

        return preg_replace('/['. $symbols . ']+/u', '', $string);
    }

    /**
     * Filters $input_array so only $keys are present.
     *
     * @param array $input_array
     * @param array $keys
     * @return array
     */
    static function filter_keys($input_array, $keys) {
        return array_intersect_key($input_array, array_flip($keys));
    }

    /**
     * Strips $keys from $input_array.
     *
     * @param array $input_array
     * @param array $keys
     * @return array
     */
    static function strip_keys($input_array, $keys) {
        return array_diff_key($input_array, array_flip($keys));
    }
}
