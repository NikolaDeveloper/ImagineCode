<?php

namespace NikolaDev\ImagineCode;

class Logger {
    const NONE = 99999;
    const FATAL = 50000;
    const ERROR = 40000;
    const WARN = 30000;
    const INFO = 20000;
    const DEBUG = 10000;
    const VERBOSE = 5000;
    const ALL = 0;

    public $path;
    public $name;
    protected $__attached_to = array();
    protected $__level;
    protected $__kb_size_limit;

    public $last_message;

    /**
     * @param string $name - logger name
     * @param string $path - path/to/log/file
     * @param int $level - logging level
     * @param float|int $kb_size_limit - max log file size (0 = unlimited)
     */
    function __construct($name, $path, $level = Logger::DEBUG, $kb_size_limit = 10 * 1024) {
        $this->name = $name;
        $this->path = $path;
        $this->__level = $level;
        $this->__kb_size_limit = (int)$kb_size_limit;
    }

    /**
     * Set logging level
     * @param int $level
     */
    function set_level($level) {
        $this->__level = $level;
    }

    /**
     * Get logging level
     * @return int
     */
    function get_level() {
        return $this->__level;
    }

    /**
     * Check if logging level matches set level
     *
     * @param int $level
     * @return bool
     */
    function can_log($level) {
        return $this->__level <= $level;
    }

    /**
     * Helper function
     * @param $message
     * @param null|array $backtrace
     * @return bool|mixed
     */
    function fatal($message, $backtrace = null) {
        return $this->__write(static::FATAL, $message, $backtrace);
    }

    /**
     * Helper function
     * @param $message
     * @param null|array $backtrace
     * @return bool|mixed
     */
    function error($message, $backtrace = null) {
        return $this->__write(static::ERROR, $message, $backtrace);
    }

    /**
     * Helper function
     * @param $message
     * @param null|array $backtrace
     * @return bool|mixed
     */
    function warn($message, $backtrace = null) {
        return $this->__write(static::WARN, $message, $backtrace);
    }

    /**
     * Helper function
     * @param $message
     * @param null|array $backtrace
     * @return bool|mixed
     */
    function info($message, $backtrace = null) {
        return $this->__write(static::INFO, $message, $backtrace);
    }

    /**
     * Helper function
     * @param $message
     * @param null $backtrace
     * @return bool|mixed
     */
    function debug($message, $backtrace = null) {
        return $this->__write(static::DEBUG, $message, $backtrace);
    }

    /**
     * Helper function
     * @param $message
     * @param null|array $backtrace
     * @return bool|mixed
     */
    function verbose($message, $backtrace = null) {
        return $this->__write(static::VERBOSE, $message, $backtrace);
    }

    /**
     * Get level name by int value
     * @param int $level
     * @return string
     */
    function level_name($level) {
        if($level == static::ALL)
            return 'ALL';
        if($level <= static::VERBOSE)
            return 'VERBOSE';
        elseif($level <= static::DEBUG)
            return 'DEBUG';
        elseif($level <= static::INFO)
            return 'INFO';
        elseif($level <= static::WARN)
            return 'WARN';
        elseif($level <= static::ERROR)
            return 'ERROR';
        elseif($level <= static::FATAL)
            return 'FATAL';
        else
            return 'NONE';
    }


    /**
     * Attach output to other loggers.
     * @param Logger|Logger[] $loggers
     */
    function attach_to($loggers) {
        if(!is_array($loggers))
            $loggers = array($loggers);

        foreach($loggers as $logger) {
            if (!array_key_exists($logger->name, $this->__attached_to))
                $this->__attached_to[$logger->name] = $logger;
        }
    }

    /**
     * Detach output of this logger from other loggers.
     *
     * @param string|array|Logger|Logger[] $loggers
     */
    function detach_from($loggers) {
        if(!is_array($loggers))
            $loggers = array($loggers);

        foreach($loggers as $logger) {
            $key = is_a($logger, 'NikolaDev\ImagineCode\Logger') ? $logger->name :  $logger;
            unset($this->__attached_to[$key]);
        }
    }

    protected function __write($level, $message, $backtrace = null) {
        if($this->__level > $level)
            return true;

        $lvl = $this->level_name($level);
        $date = date('Y-m-d H:i:s');

        $prefix = "[$date] - $lvl - ";

        if(is_array($message) || is_object($message))
            $message = print_r($message, true);

        if(is_array($backtrace) && !empty($backtrace)) {
            $caller = end($backtrace);

            if(is_array($caller)) {
                $class = isset($caller['class']) && isset($caller['type']) ? $caller['class'] . $caller['type'] : '';

                $message .= PHP_EOL . sprintf('[Caller: %s] [File: %s:%d]', $class . $caller['function'], $caller['file'], $caller['line']);
            }
        }

        $output = $prefix . $message . PHP_EOL;

        $res = $this->__write_to_file($output);

        if(count($this->__attached_to)) {
            $prefix = "[$date] [$this->name] - $lvl - ";
            $output = $prefix . $message . PHP_EOL;
            /** @var Logger $logger */
            foreach ($this->__attached_to as $name => $logger) {
                $logger->__write_from_attached($level, $output);
            }
        }
        if($res)
            $this->last_message = $output;

        return $res;
    }
    protected function __write_from_attached($level, $message) {
        if($this->__level > $level)
            return true;

        return $this->__write_to_file($message);
    }

    protected function __write_to_file($message) {
        if(!$this->__create_file())
            return false;

        if(!$this->__truncate_log_file(strlen($message)))
            return false;

        try {
            @file_put_contents($this->path, $message, FILE_APPEND);
            return $message;
        }
        catch (\Exception $exception) {
            return false;
        }
    }
    protected function __truncate_log_file($new_message_size = 0) {
        if($this->__kb_size_limit < 1)
            return true;

        if(empty($this->path) || !file_exists($this->path))
            return true;

        $quota = $this->__kb_size_limit * 1024;

        clearstatcache();
        $size = filesize($this->path);

        if($size + $new_message_size < $quota)
            return true;

        //Truncate file to half the size of actual quota, so we don't repeat this task too often.
        $quota = $quota * 0.5 - $new_message_size;


        $f = fopen($this->path, "r+");
        if(!$f)
            return false;

        ftell($f);
        fseek($f, -$quota, SEEK_END);
        $drop = fgets($f);
        $offset = ftell($f);

        for($x = 0; $x < $quota; $x++) {
            fseek($f, $x + $offset);
            $c = fgetc($f);
            fseek($f, $x);
            fwrite($f, $c);
        }

        ftruncate($f, $quota - strlen($drop));
        fclose($f);

        return true;
    }

    protected function __create_file() {
        if(is_dir($this->path))
            return false;

        if(file_exists($this->path))
            return is_writable($this->path);

        if(!is_dir(dirname($this->path)) && !mkdir(dirname($this->path), 0777, true))
            return false;

        touch($this->path);
        chmod($this->path, 0777);

        if(!file_exists($this->path) || !is_writable($this->path))
            return false;

        return true;
    }
}