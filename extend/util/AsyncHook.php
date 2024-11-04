<?php

namespace util;


class AsyncHook
{
    private static $hook_list = array();
    private static $hooked = false;

    /**
     * hook函数fastcgi_finish_request执行
     *
     * @param callback $callback
     * @param array $params
     */
    public static function hook($callback, $params = [])
    {
        self::$hook_list[] = array('callback' => $callback, 'params' => $params);

        if (self::$hooked == false) {
            self::$hooked = true;
            register_shutdown_function(array(__CLASS__, '__run'));
        }
    }

    /**
     * 由系统调用
     *
     * @return void
     */
    public static function __run()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        if (empty(self::$hook_list)) {
            return;
        }

        foreach (self::$hook_list as $hook) {
            $callback = $hook['callback'];
            $params   = $hook['params'];
            call_user_func_array($callback, $params);
        }
    }

}