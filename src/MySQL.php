<?php

namespace NikolaDev\ImagineCode;

define('IC_MYSQLI_ASSOC', MYSQLI_ASSOC);
define('IC_MYSQLI_NUM', MYSQLI_NUM);
define('IC_MYSQLI_BOTH', MYSQLI_BOTH);
define('IC_MYSQLI_OBJECT', 30);

Class MySQL {

    public $query_tracker = [];

    private $_is_dead = false;

    protected $_track_queries = false;

    /* Configuration */
    /** @see $this->auto_config() */

    /** @var bool - Show errors in browser. */
    public $show_errors;

    public $die_on_error = true;

    /** @var Logger $logger */
    public $logger;

    /** @var \mysqli - Holds mysqli instance */
    public $mysqli;

    private $server;
    private $username;
    private $password;
    private $database_name;

    /** @var mixed Contains last_insert_id from the last query. */
    private $insert_id;

    /** @var string Contains last mysql query string. */
    private $last_query;

    private $last_error = ['type'=>null, 'message'=>null];

    private $request_id = null;

    /**
     * MySQL constructor.
     *
     * @param string $server            MySQL server address
     * @param string $username          MySQL username
     * @param string $password          MySQL password
     * @param string $database_name     Database name
     * @param Logger|null $logger       Logger instance
     * @param bool $auto_connect        If TRUE, it will automatically connect to database with default settings.
     */
    function __construct($server, $username, $password, $database_name, $logger = null, $auto_connect = false) {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->database_name = $database_name;
        $this->logger = $logger;

        $this->request_id = md5(uniqid() . rand(1,99999));

        $this->auto_config();

        if($auto_connect)
            $this->connect();
    }

    function start_tracking() {
        return $this->_track_queries = true;
    }
    function stop_tracking() {
        return $this->_track_queries = false;
    }
    function clear_track() {
        $this->query_tracker = [];
    }

    /**
     * Connect to MySQL database
     */
    function connect() {
        try {
            $this->mysqli = mysqli_connect($this->server, $this->username, $this->password, $this->database_name)
            or
            $this->handle_error('fatal', mysqli_connect_error());
        }
        catch (\Exception $ex) {
            $this->handle_error('exception', $ex);
        }
    }

    /* Queries */

    /**
     * @param string $query     Query statement (ex. "SELECT * FROM `users` WHERE user_login=%s OR email=%s LIMIT 0,5").
     * @param mixed $values,... Values to be replaced in query statement (ex. 'root', 'root@localhost.com' ).
     *
     * @return bool|\mysqli_result
     */
    function query($query, ...$values) {
        try {
            $query = call_user_func_array([$this, 'prepare'], func_get_args());
            if(!$query)
                return false;
        }
        catch(\Exception $e) {
            return false;
        }

        return $this->query_prepared($query);
    }

    /**
     * Execute prepared mysql query.
     *
     * @param string $query
     *
     * @return bool|\mysqli_result
     */
    function query_prepared($query) {
        if(!$query) {
            if($this->logger) $this->logger->error(
                sprintf('[REQUEST: %s] Could not execute empty query.', $this->request_id),
                debug_backtrace(2)
            );
            return false;
        }

        $now = microtime(true);
        //Execute query.
        $this->last_query = $query;

        try {
            $result = $this->mysqli->query($query);
        }
        catch(\Exception $e) {
            $result = false;
        }

        if(!$result) {
            $error = mysqli_error($this->mysqli);
            if(stripos($error, 'deadlock') !== false) {
                for($retries = 1; $retries <= 20; $retries++) {
                    usleep(100000);
                    $result = $this->mysqli->query($query);
                    if($result) {
                        break;
                    }
                }
            }
        }

        $time_elapsed = microtime(true) - $now;
        $backtrace = debug_backtrace(2);

        if($this->_track_queries) {
            $this->query_tracker[] = array(
                'time' => sprintf('%0.3f', $time_elapsed),
                'query' => str_replace(PHP_EOL, ' ', $query),
                'microtime' => $time_elapsed,
                'back' => $backtrace
            );
        }

        if($this->logger) $this->logger->verbose(
            sprintf("[REQUEST: %s] [TIME: %0.3f seconds] QUERY:\n%s", $this->request_id, $time_elapsed, $query),
            $backtrace
        );

        if(!$result) {
            //Reset insert_id just in case.
            $this->insert_id = 0;
            if($this->logger) $this->logger->error(
                mysqli_error($this->mysqli) . "\nQUERY: " . $query,
                $backtrace
            );
            return false;
        }

        $this->insert_id = $this->mysqli->insert_id;
        return $result;
    }

    /**
     * Get last insert id.
     *
     * @return mixed
     */
    function last_insert_id() {
        return $this->insert_id;
    }
    function last_query() {
        return $this->last_query;
    }

    /**
     * Prepares MySQL statement using RegEx and vsprintf.
     * Allowed tags in query are: %s (string), %d (integer), %f (float).
     *
     * Returns FALSE on failure, in case params are found in query, but they're missing values in $values array.
     * On success, returns prepared query ready for execution.
     *
     * @NOTE DO NOT wrap tags in quotes, function will determine where quotes are required, based on value type
     *       and do it for you.
     *
     * @param string $query     Query statement (ex. "SELECT * FROM `users` WHERE user_login=%s OR email=%s LIMIT 0,5").
     * @param mixed $values,...  Values to be replaced in query statement (ex. 'root', 'root@localhost.com' ).
     *
     * @throws \Exception
     * @return bool|string
     */
    function prepare($query, ...$values) {
        $args = func_get_args();
        $query = array_shift($args);
        $values = $args;
        if($values !== null && !is_array($values)) {
            $err_msg = sprintf('Param $values must be an array, %s given.', gettype($values));
            $this->handle_error('prepare', $err_msg, debug_backtrace(2), false);
            return false;
        }
        preg_match_all("/(%s)|(%d)|(%f)/", $query, $matches);

        if(is_array($matches[0]) && !empty($matches[0])) {
            $params = $matches[0];
            //There are more params than values. Log error and return false.
            if(count($params) != count($values)) {
                $err_msg = sprintf('Some parameters are missing values expected in $values array. Params found: %d, values found: %d',
                    count($params), count($values));
                $this->handle_error('prepare', $err_msg, debug_backtrace(2), false);
                return false;
            }

            for($x = 0; $x < count($params); $x++) {
                $values[$x] = $this->format_value($values[$x], $params[$x], true);
            }

            $query = vsprintf($query, $values);
        }

        return $query;
    }

    /**
     * Retrieves row data from table.
     *
     * @param string $prepared_query     PREPARED Query statement (ex. "SELECT * FROM `users` WHERE user_login=1 OR email='a@b.com' LIMIT 0,5").
     * @param int $offset               Retrieve specific row by it's index, starting from zero.
     * @param int $output               IC_MYSQLI_OBJECT | IC_MYSQLI_ASSOC | IC_MYSQLI_NUM | IC_MYSQLI_BOTH
     *
     * @return array|mixed|object
     */
    function select_row($prepared_query, $offset = 0, $output = IC_MYSQLI_OBJECT) {
        $result = $this->query_prepared($prepared_query);

        if(!$result)
            return false;

        $x = 0;
        while($x < $offset) {
            $x++;
            $result->fetch_row();
        }

        switch ($output) {
            case IC_MYSQLI_NUM:
                $data = $result->fetch_row();
                break;
            case IC_MYSQLI_ASSOC:
                $data = $result->fetch_assoc();
                break;
            case IC_MYSQLI_BOTH:
                $data = $result->fetch_array();
                break;
            default:
                $data = $result->fetch_object();
                break;
        }

        $result->free();

        return $data;
    }

    /**
     * Retrieve single value from statement (ex. "SELECT COUNT(*) FROM `users`").
     * Function returns NULL if no result is found.
     * Returns FALSE on failure.
     *
     * @param string $prepared_query     PREPARED Query statement (ex. "SELECT * FROM `users` WHERE user_login=1 OR email='a@b.com' LIMIT 0,1").
     * @param int $offset                Number of row to return.
     *
     * @return bool|mixed|null
     */
    function select_var($prepared_query, $offset = 0) {
        $result = $this->query_prepared($prepared_query);

        if(!$result)
            return false;

        $data = $result->fetch_row();

        if(is_array($data) && isset($data[$offset]))
            return $data[$offset];

        return null;
    }

    /**
     * Perform SELECT query and fetch data and returns it in desired format ($output) on success.
     * Returns FALSE on failure.
     *
     * @param string $prepared_query     PREPARED Query statement (ex. "SELECT * FROM `users` WHERE user_login=1 OR email='a@b.com' LIMIT 0,5").
     * @param int $output               IC_MYSQLI_OBJECT | IC_MYSQLI_ASSOC | IC_MYSQLI_NUM | IC_MYSQLI_BOTH
     *
     * @return bool|array|object
     */
    function select($prepared_query, $output = IC_MYSQLI_OBJECT) {
        $result = $this->query_prepared($prepared_query);

        if(!$result)
            return false;

        if($output != IC_MYSQLI_OBJECT)
            $data = $result->fetch_all($output);
        else {
            $data = $result->fetch_all(IC_MYSQLI_ASSOC);

            foreach($data as &$item) {
                $item = (object)$item;
            }
        }

        $result->free();

        return $data;
    }
    /**
     * Delete entry from table.
     *
     * @param string $table         Table name
     *
     * @param array $where_data     Use column=>value array scheme. Values go into WHERE part of query connected with AND (ex. WHERE column=value AND column2=value2 AND ...)
     * @param array $where_format   Provide format for every value in $where_data array, in order.
     *                              If format is not provided, script will try to detect it automatically.
     *                              Possible formats: %s (string), %d (integer), %f (float)
     *
     * @return bool|mysqli_result
     */
    function delete($table, $where_data = array(), $where_format = array()) {
        $table = $this->validate_table($table, 'delete');
        if(!$table)
            return false;

        if(!empty($where_data)) {
            $where_data = $this->validate_data($where_data, 'where_data', 'delete');
            if(!$where_data)
                return false;

            if(!empty($where_data) && empty($where_format)) {
                $where_format = array();
                foreach($where_data as $k=>$v) {
                    $where_format[] = $this->detect_format($v);
                }
            }

            $where_format = $this->validate_format($where_data, $where_format, 'where_format', 'delete');
            if(!$where_format)
                return false;
        }

        //WHERE
        $where = '';
        $x = 0;
        foreach($where_data as $column=>&$value) {
            $value = $this->format_value($value, $where_format[$x], true);
            if($x > 0)
                $where .= ' AND ';

            $where .= $column . '=' . $value;

            $x++;
        }
        if(!empty($where))
            $where = 'WHERE ' . $where;

        $query = "DELETE FROM `$table` $where;";

        return $this->query_prepared($query);
    }

    /**
     * Update entry from table.
     *
     * @param string $table         Table name
     * @param array $data           Use column=>value array scheme.
     * @param array $data_format    Provide format for every value in $data array, in order.
     *                              If format is not provided, script will try to detect it automatically.
     *                              Possible formats: %s (string), %d (integer), %f (float)
     *
     * @param array $where_data     Use column=>value array scheme. Values go into WHERE part of query connected with AND (ex. WHERE column=value AND column2=value2 AND ...)
     * @param array $where_format   Provide format for every value in $where_data array, in order.
     *                              If format is not provided, script will try to detect it automatically.
     *                              Possible formats: %s (string), %d (integer), %f (float)
     *
     * @return bool|mysqli_result
     */
    function update($table, $data, $where_data = array(), $data_format = array(), $where_format = array()) {
        $table = $this->validate_table($table, 'update');
        if(!$table)
            return false;

        $data = $this->validate_data($data, 'data', 'update');
        if(!$data)
            return false;

        if(!empty($data) && empty($data_format)) {
            $data_format = array();
            foreach($data as $k=>$v) {
                $data_format[] = $this->detect_format($v);
            }
        }

        $data_format = $this->validate_format($data, $data_format, 'format', 'update');
        if(!$data_format)
            return false;

        if(!empty($where_data)) {
            $where_data = $this->validate_data($where_data, 'where_data', 'update');
            if(!$where_data)
                return false;

            if(!empty($where_data) && empty($where_format)) {
                $where_format = array();
                foreach($where_data as $k=>$v) {
                    $where_format[] = $this->detect_format($v);
                }
            }

            $where_format = $this->validate_format($where_data, $where_format, 'where_format', 'update');
            if(!$where_format)
                return false;
        }


        //SET
        $set = '';

        $x = 0;
        foreach($data as $column=>&$value) {
            $value = $this->format_value($value, $data_format[$x], true);
            if($x > 0)
                $set .= ', ';

            $set .= "`$column` = $value";

            $x++;
        }

        $set = 'SET ' . $set;

        //WHERE
        $where = '';
        $x = 0;
        foreach($where_data as $column=>&$value) {
            $value = $this->format_value($value, $where_format[$x], true);
            if($x > 0)
                $where .= ' AND ';

            $where .= $column . '=' . $value;

            $x++;
        }
        if(!empty($where))
            $where = 'WHERE ' . $where;

        $query = "UPDATE `$table` $set $where;";

        return $this->query_prepared($query);
    }

    /**
     * Insert entry to table.
     *
     * @param string $table         Table name
     * @param array $data           Use column=>value array scheme.
     * @param array $data_format    Provide format for every value in $data array, in order.
     *                              If format is not provided, script will try to detect it automatically.
     *                              Possible formats: %s (string), %d (integer), %f (float)
     * @param bool $query_only      Return query without executing.
     * @return bool
     */
    function insert($table, $data, $data_format = array(), $query_only = false) {
        return $this->__insert_replace('INSERT', $table, $data, $data_format, $query_only);
    }

    /**
     * Replace entry in table.
     *
     * @param string $table         Table name
     * @param array $data           Use column=>value array scheme.
     * @param array $data_format    Provide format for every value in $data array, in order.
     *                              If format is not provided, script will try to detect it automatically.
     *                              Possible formats: %s (string), %d (integer), %f (float)
     * @param bool $query_only      Return query without executing.     *
     * @return bool
     */
    function replace($table, $data, $data_format = array(), $query_only = false) {
        return $this->__insert_replace('REPLACE', $table, $data, $data_format, $query_only);
    }

    /**
     * Insert or replace entry from table.
     *
     * @param string $method        INSERT or REPLACE
     * @param string $table         Table name
     * @param array $data           Use column=>value array scheme.
     * @param array $data_format    Provide format for every value in $data array, in order.
     *                              If format is not provided, script will try to detect it automatically.
     * @param bool $query_only      Return query without executing.
     * @return bool|mysqli_result
     */
    private function __insert_replace($method, $table, $data, $data_format = array(), $query_only = false) {
        if(!in_array(strtoupper($method), array('INSERT', 'REPLACE')))
            return false;

        $table = $this->validate_table($table, strtolower($method));
        if(!$table)
            return false;

        $data = $this->validate_data($data, 'data', strtolower($method));
        if(!$data)
            return false;

        if(!empty($data) && empty($data_format)) {
            $data_format = array();
            foreach($data as $k=>$v) {
                $data_format[] = $this->detect_format($v);
            }
        }

        $data_format = $this->validate_format($data, $data_format, 'format', strtolower($method));
        if(!$data_format)
            return false;

        $x = 0;
        foreach($data as $column=>&$value) {
            $value = $this->format_value($value, $data_format[$x], true);

            $x++;
        }

        $columns = implode("`, `", $this->esc_array(array_keys($data)));
        $values = implode(", ", array_values($data));

        $query = "$method INTO `$table` (`$columns`) VALUES($values)";
        if($query_only)
            return $query;

        return $this->query_prepared($query);
    }

    function format_value($value, $format, $escape = false) {
        if($value === null)
            return 'NULL';

        if(is_array($value) || is_object($value))
            $this->handle_error('exception', new \Exception(sprintf('Could not format value of invalid type (%s), %s.', gettype($value), print_r($value, true))), true);


        if($escape)
            $value = $this->esc($value);

        switch ($format) {
            case '%s':
                $value = "'$value'";
                break;
            case '%d':
                $value = (int)$value;
                break;
            case '%f':
                $value = (float)$value;
        }

        return $value;
    }
    /**
     * @param string $table
     * @param string $function_name
     *
     * @return bool|string
     */
    private function validate_table($table, $function_name) {
        if(!is_string($table) || empty($table)) {
            if($this->logger) $this->logger->error(
                '->' . $function_name .'() : Table name is empty or non-string.');
            return false;
        }

        $table = trim($table);
        $table = trim($table, '`');

        return $this->esc($table);
    }

    /**
     * @param array $data
     * @param string $variable_name
     * @param string $function_name
     *
     * @return bool|array
     */
    private function validate_data($data, $variable_name, $function_name) {
        if(!is_array($data)) {
            if($this->logger) $this->logger->error(
                '->' . $function_name .'() : $' . $variable_name . ' must be a non-empty array of column=>value pairs.');

            return false;
        }

        return $data;
    }

    /**
     * @param array $data
     * @param array $format
     * @param string $variable_name
     * @param string $function_name
     *
     * @return bool|array
     */
    private function validate_format($data, $format, $variable_name, $function_name) {
        if(!is_array($format) || count($data) > count($format)) {
            if($this->logger) $this->logger->error(
                '->' . $function_name .'() : $' . $variable_name . ' must be a non-empty array containing variable formats for every value in corresponding data array. ' .
                sprintf('Provided %d items, required %d.', count($format), count($data)));

            return false;
        }

        $format = $this->__remove_extra_formats($data, $format);
        if($this->__has_illegal_format($format)) {
            if($this->logger) $this->logger->error(
                '->' . $function_name .'() : $' . $variable_name . ' contains illegal items. Use only %s (string), %d (integer) and %f (float).');

            return false;
        }

        return $format;
    }

    function is_dead() {
        return $this->_is_dead;
    }
    /**
     * Remove any extra items from $format array.
     *
     * @param array|int $data
     * @param array $format
     *
     * @return mixed
     */
    private function __remove_extra_formats($data, $format) {
        $allowed = is_array($data) ? count($data) : (int)$data;
        if(count($format) <= $allowed) {
            array_splice($format, $allowed);
        }
        return $format;
    }
    private function __has_illegal_format($format) {
        $allowed = $this->allowed_format();
        foreach($format as $f) {
            if(!in_array($f, $allowed))
                return true;
        }
        return false;
    }

    private function allowed_format() {
        return array('%d', '%s', '%f');
    }

    function esc($string) {
        return $this->mysqli->real_escape_string($string);
    }
    function esc_array($array) {
        foreach($array as &$item) {
            $item = $this->esc($item);
        }
        return $array;
    }

    function detect_format($value) {
        if(is_int($value) || is_bool($value))
            return '%d';

        if(is_float($value))
            return '%f';

        return '%s';
    }
    function auto_config() {
        $is_local = $this->is_local_env();

        if($is_local) {
            $this->show_errors = true;
            if($this->logger) $this->logger->set_level(Logger::DEBUG);
        }
        else {
            $this->show_errors = false;
            if($this->logger) $this->logger->set_level(Logger::WARN);
        }

    }

    function is_local_env() {
        if(defined('LOCAL_ENV'))
            return (bool)LOCAL_ENV;

        if(Utils::is_cli())
            return false;

        $local = array('::1', '127.0.0.1', 'localhost', '192.168.0.', '192.168.1.');
        foreach($local as $addr) {
            if(stripos(@$_SERVER['SERVER_ADDR'], $addr) !== false)
                return true;
        }
        return false;
    }

    function get_error() {
        return mysqli_error($this->mysqli);
    }

    function get_last_error() {
        return $this->last_error;
    }

    /**
     * Handle mysql errors.
     *
     * @param string $type - Could be 'mysql_connect' / 'fatal' / 'exception'
     * @param string|\Exception|mixed $message
     * @param bool $dont_die - Ignores $this->die_on_error if set to true.
     *
     * @throws \Exception
     */
    private function handle_error($type, $message, $backtrace = null, $dont_die = false) {
        $this->last_error = array('type'=>$type, 'message'=>$message);

        switch($type) {
            //Die on FATAL && MYSQL_CONNECT errors
            case 'fatal':
            case 'mysql_connect':
                $this->_is_dead = true;
                if($this->logger) $this->logger->fatal($message, $backtrace);
                break;
            default:
                if($this->logger) $this->logger->error($message, $backtrace);
                break;
        }

        if($this->show_errors) {
            if(is_string($message) || is_integer($message) || is_float($message)) {
                echo $message;
            }
            elseif(is_a($message, 'Exception')) {
                throw $message;
            }
            else {
                var_dump($message);
            }
        }

        if($this->die_on_error && !$dont_die)
            die();
    }

    function print_results($rows, $show_number_column = false, $start_number = 1) {
        if(empty($rows)) {
            echo 'No results.';
            return;
        }
        ?>
        <table border="1" style="width: 100%; border-collapse: collapse;" class="nano-mysql-output-table">
            <thead>
            <tr>
                <?php if($show_number_column) : ?>
                    <th>#</th>
                <?php endif; ?>
                <?php foreach($rows[0] as $k=>$v) : ?>
                    <th><?php echo $k?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php
            $x = $start_number;
            foreach($rows as $row) : ?>
                <tr>
                    <?php if($show_number_column) : ?>
                        <th><?php echo $x; ?>.</th>
                    <?php endif; ?>
                    <?php foreach($row as $k=>$v) : ?>
                        <td><?php echo $v?></td>
                    <?php endforeach; ?>
                </tr>
                <?php
                $x++;
            endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
