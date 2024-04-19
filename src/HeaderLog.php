<?php

/**
 * User: 苏翔
 * Date: 2024/4/19 10:27
 */

namespace Sux789\HeaderLog;

use FB;

class HeaderLog
{
    const MAX_VAR_LEN = 300;
    const LOG_AT_LEN = 30;
    // Debug status and instance
    private static $open = false;

    // Tables for logging
    private static $timeTable = [];
    private static $logTable = [];
    private static $dbTable = [];
    private static $cacheTable = [];
    private static $serviceTable = [];

    // Begin time and debug level
    private static $beginTime;
    private static $cache_total_times = 0;

    /**
     * Starts the debugging.
     */
    public static function start()
    {
        self::$open = true;
        self::$beginTime = microtime(true);
    }

    /**
     * Checks if debug is open.
     * @return bool Debug status.
     */
    static function isOpen()
    {
        return self::$open && self::isInFirePHP();
    }

    /**
     * Stops the debugging.
     * @return bool Previous state.
     */
    public static function stop()
    {
        $old = self::$open;
        self::$open = false;
        return $old;
    }


    /**
     * Gets the time elapsed since start.
     * @return float Elapsed time.
     */
    public static function getTime()
    {
        return microtime(true) - self::$beginTime;
    }

    /**
     * Logs a custom message.
     * @param string $label Message label.
     * @param mixed $results Message content.
     * @param int $level Backtrace level.
     */
    public static function log($label, $results = 'Temporary Value', $level = 0)
    {
        if (!self::isOpen()) {
            return;
        }

        $callerAt = '定位不支持';// 记录文件log发生位置
        $level = intval($level);
        if ($level < 2) {
            $limit = $level + 1;
            $t = debug_backtrace(0, $limit);
            $arr = $t[$level];
            $callerAt = $arr['file'] . ':' . $arr['line'];
        }

        $callerAt = substr($callerAt, 0 - self::LOG_AT_LEN);
        $results = self::formatResult($results);

        if ($results === 'Temporary Value') {
            array_push(self::$logTable, array('[临时调试]', $label, $callerAt));
        } else {
            array_push(self::$logTable, array($callerAt, $label, $results));
        }
    }


    /**
     * 记录数据库查询操作执行时间
     * @param float $times
     * @param string $sql
     * @param mixed $ext
     */
    public static function db($times, $sql, $ext)
    {
        if (self::isOpen()) {
            array_push(self::$dbTable, array($times, $sql, $ext));
        }
    }

    /**
     * 记录service调用情况
     * @param float $times
     * @param string $service
     * @param string $method
     * @param array $args
     * @param string $cache
     * @param mixed $results
     */
    public static function service($times, $service, $method, $args, $cache = '', $results = null)
    {
        if (self::isOpen()) {
            $results = self::formatResult($results);
            array_push(self::$serviceTable, array($times, $service, $method, $args, $cache, $results));
        }

    }

    /**
     * 大数据量只显示部分
     * @param mixed $results
     * @return string|mixed
     */
    static function formatResult($results)
    {

        $str = var_export($results, true);
        $str = str_replace(["\r\n", "\r", "\n", " ", "\t"], "", $str);
        $len = strlen($str);
        $tobeHandle = $len > self::MAX_VAR_LEN;
        if (!$tobeHandle) {
            $tobeHandle = is_array($results) && count($results) != count($results, 1);
        }
        if (is_object($results)) {
            $tobeHandle = true;
        }

        if ($tobeHandle) {
            return substr($str, 0, self::MAX_VAR_LEN);
        } else {
            return $results;
        }

    }


    /**
     * 缓存查询执行时间
     * @param array $server 缓存服务器及端口列表
     * @param string $key 缓存所使用的key
     * @param float $times 花费时间
     * @param mixed $results 查询结果
     */
    public static function cache($server, $key, $times, $results, $method = null)
    {
        if (false === self::$open) {
            return;
        }
        if (is_string($results) && strlen($results) > 256) $results = substr($results, 0, 256) . '...(length:' . strlen($results) . ')';
        array_push(self::$cacheTable, array($server, $key, $times, $results, $method));
    }

    /**
     * Records program execution time.
     * * @param string $desc Description
     * * @param mixed $caller $caller
     */
    public static function time($desc = '', $caller = '')
    {
        if (self::isOpen()) {
            if ($desc == '') {
                $desc = 'run-time';
            }
            if ($caller == '') {
                $t = debug_backtrace(1);
                $caller = $t[0]['file'] . ':' . $t[0]['line'];
            } elseif ($caller == 'full') {
                $caller = debug_backtrace(5);
            }
            array_push(self::$timeTable, array($desc, self::getTime(), $caller));
        }
    }

    /**
     * Checks if the client is using FirePHP.
     * 修改目的： 1，线上调试更为安全，只有firephp打开而且使用中其面板情况下，才进行调试
     *          2，如果滥用，导致header过大502 Bad Gateway,只会影响打开使用firephp面板的当前session
     * HTTP_USER_AGENT 是以前逻辑，HTTP_X_FIREPHP_VERSION是下面选项逻辑，HTTP_X_WF_PROTOCOL_1调试结果逻辑，参考下面浏览器插件选项
     * Enable UserAgent Request Header - Modifies the User-Agent request header by appending FirePHP/0.5.
     * Enable FirePHP Request Header - Adds a X-FirePHP-Version: 0.4 request header.
     */
    public static function isInFirePHP()
    {
        static $rt = null;
        if (null === $rt) {
            $rt = (isset($_SERVER['HTTP_X_FIREPHP_VERSION']) || isset($_SERVER['HTTP_X_WF_PROTOCOL_1']));
            if (!$rt && isset($_SERVER['HTTP_USER_AGENT'])) {
                $rt = (bool)preg_match('/FirePHP/i', $_SERVER['HTTP_USER_AGENT']);
            }
        }
        return $rt;
    }

    /**
     * 显示调试信息
     */
    public static function show()
    {

        if (self::isOpen()) {
            self::stop();//防止再次输出
        } else {
            return;
        }

        // 执行时间
        if (count(self::$timeTable)) {
            array_unshift(self::$timeTable, ['Description', 'Time', 'Caller']);
            self::sendToFb('This Page Spend Times ' . self::getTime(), self::$timeTable);
        }

        if ($count = count(self::$logTable)) {
            array_unshift(self::$logTable, ['file:line', 'Label', 'debug变量结果']);
            self::sendToFb("Custom Log Object $count", self::$logTable);
        }


        // 数据执行时间
        if ($count = count(self::$dbTable)) {
            $totalTimeSpent = array_sum(array_column(self::$dbTable, 0));
            array_unshift(self::$dbTable, array('耗时', 'sql'));
            self::sendToFb($count . ' SQL queries took ' . $totalTimeSpent . ' seconds', self::$dbTable);
        }

        //Cache执行时间
        if (count(self::$cacheTable) > 0) {
            self::sendToFb(self::$cacheTable, self::$cache_total_times);
        }

        // 服务执行时间
        if ($count = count(self::$serviceTable)) {
            $totalTimeSpent = array_sum(array_column(self::$serviceTable, 0));
            array_unshift(self::$serviceTable, array('耗时', 'Service', 'Method', '参数', '命中缓存|事务', 'Results'));
            self::sendToFb("{$count}服务执行{$totalTimeSpent}秒", self::$serviceTable);
        }

    }

    static function sendToFb($lable, $data = [])
    {
        FB::table($lable, $data);
    }
}