<?php

namespace NikolaDev\ImagineCode;

class Router {
    private static $base_url;
    private static $base_dir;

    private static $map = array(
        'GET'=>array(),
        'POST'=>array(),
        'PUT'=>array(),
        'DELETE'=>array(),
        'OPTIONS'=>array(),
        'CONNECT'=>array()
    );

    private static $map_dynamic = array(
        'GET'=>array(),
        'POST'=>array(),
        'PUT'=>array(),
        'DELETE'=>array(),
        'OPTIONS'=>array(),
        'CONNECT'=>array()
    );

    private static $map_404 = array(
        'GET'=>array(),
        'POST'=>array(),
        'PUT'=>array(),
        'DELETE'=>array(),
        'OPTIONS'=>array(),
        'CONNECT'=>array()
    );

    private static $request_path;
    private static $request_headers;
    private static $is_dynamic = false;
    private static $dynamic_params = array();
    private static $dynamic_path;

    /**
     * Register route callback for method / path.
     *
     * @param string|array 	$method - HTTP request method or array of methods.
     *                                You can pass an array to apply route to multiple HTTP request methods,
     *                                or you can pass formatted string like "POST|GET|..." or you can pass
     *                                "ALL" to apply route to all available HTTP request methods.
     *
     * @param string $path          - Relative path relative to your base url.
     *								  Dynamic paths are supported, you can define them by using wildcards
     *                                such as "/user/{user_id}/profile". You can put anything between { and }
     *                                and you can define multiple variables (ex. "/forum/{forum_id}/topic/{topic_id}").
     *
     * @param callable $callable	- Callable method to be executed on self::response() if path matches.
     *                           	  If you are routing dynamic path, make sure your callback function accepts the same
     *                           	  number of params as in your dynamic path.
     */
    static function route($method, $path, $callable) {
        if(!preg_match_all('/\{(.*?)\}/i', $path))
            self::_route(self::$map, $method, $path, $callable);
        else
            self::_route(self::$map_dynamic, $method, $path, $callable);
    }

    /**
     * Remove callback for specific method / path.
     *
     * @param string|array 	$method - HTTP request method or array of methods.
     *                                You can pass an array to apply route to multiple HTTP request methods,
     *                                or you can pass formatted string like "POST|GET|..." or you can pass
     *                                "ALL" to apply route to all available HTTP request methods.
     *
     * @param string $path          - Path to be removed.
     */
    static function unroute($method, $path) {
        self::_unroute(self::$map, $method, $path);
    }

    /**
     * Register callback for "not found" event. In case requested uri is not routed,
     * this $callable will handle response.
     *
     * @param string|array 	$method - HTTP request method or array of methods.
     *                                You can pass an array to apply route to multiple HTTP request methods,
     *                                or you can pass formatted string like "POST|GET|..." or you can pass
     *                                "ALL" to apply route to all available HTTP request methods.
     *
     * @param callable $callable	- Callable method to be executed on self::response() if requested uri is not routed.
     */
    static function route_404($method, $callable) {
        self::_route(self::$map_404, $method, 0, $callable);
    }

    /**
     * Remove callback for "not found" event for specific HTTP request method.
     *
     * @param string|array 	$method - HTTP request method or array of methods.
     *                                You can pass an array to apply route to multiple HTTP request methods,
     *                                or you can pass formatted string like "POST|GET|..." or you can pass
     *                                "ALL" to apply route to all available HTTP request methods.
     */
    static function unroute_404($method) {
        self::_unroute(self::$map_404, $method, 0);
    }

    static function set_ref($url = null) {
        if($url === null)
            $url = self::current_uri();

        $_SESSION['_router_referrer'] = $url;
    }

    static function get_ref() {
        if(isset($_SESSION['_router_referrer']))
            return $_SESSION['_router_referrer'];

        return null;
    }

    static function client_ip() {
        return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
    }

    static function replace_dynamic_tags($path, $replace_with_params) {
        return preg_replace_callback('/\{(.*?)\}/mi', function($matches) use (&$replace_with_params) {
            return array_shift($replace_with_params);
        }, $path);
    }
    /**
     * Returns a response based on HTTP request and requested uri.
     *
     * If requested uri is not routed, function will trigger callback registered using self::route_404 method.
     * If that fails as well, function returns FALSE.
     *
     * @return bool|mixed
     */
    static function respond() {
        $path = self::current_path();
        $method = self::get_request_method();

        //Static first
        if(array_key_exists($method, self::$map) && array_key_exists($path, self::$map[$method])) {
            $callable = self::$map[$method][$path];

            if(is_callable($callable))
                return call_user_func($callable);
        }
        //Dynamic
        if(array_key_exists($method, self::$map_dynamic) && !empty(self::$map_dynamic[$method])) {
            $path_fragments = explode('/', $path);
            foreach(self::$map_dynamic[$method] as $d_path=>$callback) {
                $d_path_fragments = explode('/', $d_path);
                if(count($d_path_fragments) != count($path_fragments))
                    continue;

                $params = array();
                $match = true;
                for($x = 0; $x < count($d_path_fragments); $x++) {
                    //is param?
                    if(preg_match('/^\{(.*?)\}$/i', $d_path_fragments[$x])) {
                        $params[] = $path_fragments[$x];
                        continue;
                    }
                    if($d_path_fragments[$x] != $path_fragments[$x]) {
                        $match = false;
                        break;
                    }
                }
                //found?
                if($match) {
                    self::$dynamic_params = $params;
                    self::$dynamic_path = $d_path;
                    self::$is_dynamic = true;
                }
            }

            if(self::$dynamic_path != '' && array_key_exists(self::$dynamic_path, self::$map_dynamic[$method])) {
                $callable = self::$map_dynamic[$method][self::$dynamic_path];

                if(is_callable($callable))
                    return call_user_func_array($callable, self::get_params());
            }
        }
        //404
        if(array_key_exists($method, self::$map_404) && array_key_exists(0, self::$map_404[$method])) {
            $callable = self::$map_404[$method][0];

            if(is_callable($callable))
                return call_user_func($callable);

        }

        //default 404 handler
        http_response_code(404);
        echo "Not found.";

        return false;
    }

    /**
     * Checks if current requested uri is dynamic uri.
     *
     * Function is not available until self::respond() is fired.
     *
     * @return bool
     */
    static function is_dynamic() {
        return self::$is_dynamic;
    }

    /**
     * Returns routed path string based on current HTTP request method.
     * (ex. "/user/{user_id}/profile" if current request uri is "/user/31/profile")
     *
     * Function is not available until self::respond() is fired.
     *
     * @return mixed
     */
    static function get_dynamic_path() {
        if(!empty(self::$dynamic_path))
            return self::$dynamic_path;

        return self::current_path();
    }

    /**
     * Returns array of HTTP request headers.
     *
     * @return array|false
     */
    static function get_request_headers() {
        if(is_array(self::$request_headers))
            return self::$request_headers;

        if(function_exists('getallheaders')) {
            $headers = getallheaders();
        }
        else {
            $headers = array();
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        self::$request_headers = !is_array($headers) ? array() : $headers;

        return self::$request_headers;
    }

    /**
     * Get dynamic params from request uri.
     * Works only if self::is_dynamic() is TRUE.
     *
     * Function is not available until self::respond() is fired.
     *
     * @return array
     */
    static function get_params() {
        return self::$dynamic_params;
    }
    static function get_param($index) {
        $p = self::get_params();
        if(array_key_exists($index, $p))
            return $p[$index];

        return null;
    }
    static function get_query_params() {
        switch(self::get_request_method()) {
            case 'GET':
                $arr = $_GET;
                break;
            case 'POST':
                $arr = $_POST;
                break;
            default:
                $arr = $_REQUEST;
                break;
        }
        if(!is_array($arr))
            $arr = array();

        if(empty($arr)) {
            $arr = file_get_contents('php://input');
            $arr = @json_decode($arr, true);
            if($arr === null)
                return false;

            return $arr;
        }
        else {
            foreach($arr as $k=>$v) {
                $json_v = @json_decode($v, true);
                if($json_v !== null)
                    $arr[$k] = $json_v;
            }
        }
        return $arr;
    }
    static function get_query_param($key, $default = null) {
        $arr = self::get_query_params();
        if($arr === false)
            return false;

        if(is_array($arr) && array_key_exists($key, $arr))
            return $arr[$key];

        return $default;
    }

    /**
     * Get current HTTP request method.
     *
     * @return string
     */
    static function get_request_method() {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    static function is_path($request_uri) {
        if(!is_array($request_uri))
            $request_uri = array($request_uri);

        foreach($request_uri as $uri) {
            $uri = trim($uri);
            if(substr($uri, 0, 1) != '/')
                $uri = '/' . $uri;

            if( self::current_path() == $uri)
                return true;
        }
        return false;
    }

    /**
     * Get current request uri (without self::$baseDir part and url params).
     *
     * @return string
     */
    static function current_path() {
        if( self::$request_path !== null)
            return self::$request_path;

        $uri = $_SERVER['REQUEST_URI'];

        if(strlen(self::$base_dir) && substr($uri, 0, strlen(self::$base_dir)) == self::$base_dir) {
            $uri = substr($uri, strlen(self::$base_dir));
        }
        if(strpos($uri, '?') !== false) {
            $uri = explode('?', $uri);
            $uri = $uri[0];
        }
        if(strpos($uri, '&') !== false) {
            $uri = explode('&', $uri);
            $uri = $uri[0];
        }
        if($uri != '/')
            $uri = rtrim($uri, '/');

        self::$request_path = $uri;

        return self::$request_path;
    }

    /**
     * Set base url of your application.
     *
     * @param $base_url
     */
    static function set_base_url($base_url) {
        self::$base_url = $base_url;
    }
    static function get_base_url() {
        return self::$base_url;
    }

    /**
     * If your application is not located in root directory,
     * use relative path from root directory.
     *
     * Example:
     * "/myApp" if your application is located in "/home/myUser/public_html/myApp"
     * where "/home/myUser/public_html" is root directory of your server.
     *
     * @param string $base_dir
     */
    static function set_base_dir($base_dir) {
        self::$base_dir = $base_dir;
    }
    static function get_base_dir() {
        return self::$base_dir;
    }

    static function get_routes() {
        $routes = self::$map;
        foreach(self::$map_dynamic as $method=>$paths) {
            if(empty($paths))
                continue;

            foreach($paths as $path=>$callback) {
                $routes[$method][$path] = $callback;
            }
        }
        return $routes;
    }

    static function clear_routes() {
        $clean = array(
            'GET'=>array(),
            'POST'=>array(),
            'PUT'=>array(),
            'DELETE'=>array(),
            'OPTIONS'=>array(),
            'CONNECT'=>array()
        );
        self::$map = $clean;
        self::$map_dynamic = $clean;
        self::$map_404 = $clean;
    }

    /**
     * Perform HTTP redirect.
     *
     * @param      $path
     * @param bool $prevent_loop - Prevents infinite redirect loop.
     *
     * @return bool
     */
    static function redirect($path, $prevent_loop = true) {
        if($prevent_loop) {
            $test_path = '';

            //is local path?
            if(substr($path, 0, 1) == '/')
                $test_path = self::$base_url . $path;
            elseif(strtolower(substr($path, 0, 4)) != 'http') {
                $test_path = self::$base_url . '/' . $path;
            }
            else {
                //is local path with self::$baseUrl?
                if(strtolower(substr($path, 0, strlen(self::$base_url))) == strtolower(self::$base_url)) {
                    $test_path = $path;
                }
            }

            if(!empty($test_path)) {
                $test_path = substr($test_path, strlen(self::$base_url));
                $test_path = explode('?', $test_path);
                $test_path = $test_path[0];

                if($test_path == self::current_path())
                    return false;
            }
        }
        self::set_ref();
        header('Location: ' . $path);
        exit(0);
    }

    static function current_uri() {
        return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . @$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    private static function _route(&$map, $method, $path, $callable) {
        $method = strtoupper($method);
        if($path != '/')
            $path = rtrim($path, '/');

        if($method == 'ALL') {
            $methods = array_keys($map);
        }
        elseif(strpos($method, '|') !== false) {
            $methods = explode('|', $method);
            $methods = array_map('trim', $methods);
        }
        else {
            if(!is_array($method))
                $methods = array($method);
            else
                $methods = $method;
        }

        foreach($methods as $m) {
            if(!array_key_exists($m, $map))
                continue;

            $map[$m][$path] = $callable;
        }
    }
    private static function _unroute(&$map, $method, $path) {
        $method = strtoupper($method);

        if($method == 'ALL') {
            $methods = array_keys($map);
        }
        elseif(strpos($method, '|') !== false) {
            $methods = explode('|', $method);
            $methods = array_map('trim', $methods);
        }
        else {
            $methods = array($method);
        }
        foreach($methods as $m) {
            if(empty($m) || !array_key_exists($m, $map))
                continue;

            unset($map[$m][$path]);
        }

        return true;
    }
}
