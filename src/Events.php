<?php

namespace NikolaDev\ImagineCode;

class Events {
    /** @var array - Holds registered events */
    private static $EVENTS = array();

    /** @var array - Holds registered filters */
    private static $FILTERS = array();

    /**
     * Attach new event action to specific event.
     *
     * @uses __attach()
     *
     * @param string $event_name - Event name.
     * @param callable|string|array $event_action - Callable function which will be called when event fires.
     * @param int $priority - If there is already registered event action for this event with the same priority,
     *                        priority will increase by 1 until there's no duplicate priority for the same event.
     *
     */
    static function attach_event($event_name, $event_action, $priority = 20) {
        static::__attach('EVENT', $event_name, $event_action, $priority);
    }

    /**
     * Attach new event action to specific event.
     *
     * @uses __attach()
     *
     * @param string $filter_name - Filter name.
     * @param callable|string|array $filter_action - Callable function which will be called when filter fires.
     * @param int $priority - If there is already registered filter action for this filter with the same priority,
     *                        priority will increase by 1 until there's no duplicate priority for the same filter.
     *
     */
    static function attach_filter($filter_name, $filter_action, $priority = 20) {
        static::__attach('FILTER', $filter_name, $filter_action, $priority);
    }

    /**
     * Detach specific action from event.
     *
     * @uses __detach()
     *
     * @param $event_name - Event name.
     * @param $event_action - Action to be detached.
     */
    static function detach_event($event_name, $event_action) {
        static::__detach('EVENT', $event_name, $event_action);
    }

    /**
     * Detach specific action from filter.
     *
     * @uses __detach()
     *
     * @param $filter_name - Filter name.
     * @param $filter_action - Action to be detached.
     */
    static function detach_filter($filter_name, $filter_action) {
        static::__detach('FILTER', $filter_name, $filter_action);
    }

    /**
     * Invokes specific event and calls attached actions.
     *
     * @param $event_name - Event name.
     * @param string $args - Additional arguments passed to attached action.
     */
    static function invoke($event_name, $args = '') {
        if(!array_key_exists($event_name, static::$EVENTS) || empty(static::$EVENTS[$event_name]))
            return;

        $f = static::$EVENTS[$event_name];
        ksort($f);
        foreach($f as $data) {
            $func = $data['function'];
            if(is_callable($func) || (!is_array($func) && function_exists($func)) || (is_array($func) && method_exists($func[0], $func[1]))) {
                $a = func_get_args();
                call_user_func_array($func, array_slice($a, 1, count($a) - 1));
            }
        }
    }

    /**
     * Calls specific filter and calls attached modifier actions.
     *
     * @param $filter_name  - Filter name.
     * @param string $value - First argument is always the original value to be filtered.
     *                        Additional arguments may be passed to attached filter.
     *
     * @return mixed
     */
    static function filter($filter_name, $value = "") {
        if(!array_key_exists($filter_name, static::$FILTERS) || empty(static::$FILTERS[$filter_name]))
            return $value;

        $f = static::$FILTERS[$filter_name];
        ksort($f);
        foreach($f as $data) {
            $func = $data['function'];
            if(is_callable($func) || (!is_array($func) && function_exists($func)) || (is_array($func) && method_exists($func[0], $func[1]))) {
                $args = func_get_args();
                $args[1] = $value;
                $value = call_user_func_array($func, array_slice($args, 1, count($args) - 1));
            }
        }
        return $value;
    }

    /**
     * Detach event/filter action.
     *
     * @param string $type - Can be either EVENT or FILTER.
     * @param string $name - Event/Filter name.
     * @param string|array $action - Callable action.
     */
    protected static function __detach($type, $name, $action) {
        $prop_name = in_array(strtoupper($type), array('EVENT', 'FILTER')) ? strtoupper($type) . 'S': '';
        if(empty($prop_name))
            return;

        if(!array_key_exists($name, static::${$prop_name}))
            return;

        foreach(static::${$prop_name}[$name] as $priority=>$f) {
            if($f == $action)
                unset(static::${$prop_name}[$name][$priority]);
        }
    }

    /**
     * Attach new action to event/filter.
     *
     * @param string $type - Can be either EVENT or FILTER.
     * @param string $name - Event/Filter name.
     * @param string|array $action - Callable action.
     * @param int $priority - Execution priority.
     */
    protected static function __attach($type, $name, $action, $priority) {
        $prop_name = in_array(strtoupper($type), array('EVENT', 'FILTER')) ? strtoupper($type) . 'S' : '';
        if(empty($prop_name))
            return;

        while(isset(static::${$prop_name}[$name][$priority])) {
            $priority = (int)$priority + 1;
        }
        static::${$prop_name}[$name][$priority] = array('function'=>$action);
    }
}