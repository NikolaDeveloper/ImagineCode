<?php

namespace NikolaDev\ImagineCode;

class Error {
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const NOT_ACCEPTED = 406;
    const DUPLICATE = 409;
    const CONFLICT = 409;
    const INTERNAL = 500;

    protected $errors;
    protected $code;
    protected $data;

    public $log = false;

    function __construct($errors = array(), $code = 0, $data = array()) {
        $this->errors = is_string($errors) ? array($errors) : $errors;
        $this->code = $code;
        $this->data = $data;
    }

    static function is_error($object) {
        return is_a($object, 'ImagineCode\Error');
    }

    /**
     * @return array
     */
    function getErrors() {
        return $this->errors;
    }
    function clearErrors() {
        $this->errors = array();
    }

    /**
     * Add additional data to error object.
     *
     * @param $data
     * @param bool $append
     * @return $this
     */
    function addData($data, $append = true) {
        if(!$append)
            $this->clearData();

        if(is_array($data))
            $this->data = array_merge($this->data, $data);
        else
            $this->data[] = $data;
        return $this;
    }

    /**
     * Clear error data
     */
    function clearData() {
        $this->data = array();
    }

    /**
     * Get additional data from error object.
     * @param $key
     * @return mixed
     */
    function getData($key) {
        return @$this->data[$key];
    }

    /**
     * Add error.
     * @param $error
     * @param $message
     * @return Error
     */
    function add($error, $message) {
        global $logger;
        $this->errors[$error] = $message;
        if($this->log)
            $logger->error(sprintf('[%s] %s', $error, $message));

        return $this;
    }

    /**
     * Remove error.
     *
     * @param $error
     * @return Error
     */
    function remove($error) {
        if(isset($this->errors[$error]))
            unset($this->errors[$error]);

        return $this;
    }

    /**
     * Checks if object has errors.
     *
     * @return bool
     */
    function hasErrors() {
        return (bool)count($this->errors);
    }

    /**
     * Get error messages.
     *
     * @param string $title
     * @param bool $nl2br
     * @param bool $show_code
     * @return string
     */
    function getMessage($title = '', $nl2br = false, $show_code = true) {
        if(!$title && count($this->errors) > 1)
            $title = __('Some errors occurred:');

        if($show_code && $this->code)
            $title = trim(sprintf(__('Error %s:'), $this->code) . ' ' . $title);

        $messages = array_values($this->errors);
        if($title)
            $messages = array_merge(array($title), $messages);

        return implode($nl2br ? '<br />' : PHP_EOL, $messages);
    }

    /**
     * @param $code
     * @return Error
     */
    function setCode($code) {
        $this->code = $code;
        return $this;
    }
    function getCode() {
        return $this->code;
    }
}