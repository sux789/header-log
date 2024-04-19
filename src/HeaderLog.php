<?php

/**
 * User: 苏翔
 * Date: 2024/4/19 10:27
 */

namespace Sux789\HeaderLog;

use FB;

class HeaderLog
{
    const MAX_VAR_LEN = 300; // 防止输出过多变量导致502错误
    const LOG_AT_LEN = 30;
    /**
     * Debug开关,默认为关闭
     */
    static $open = false;

    /**
     * Debug类实例化对象
     */
    static $instance = false;
    /**
     * 运行时间显示数组
     */
    static $timeTable = array();
    /**
     * 用户自定义中间变量显示数组
     */
    static $logTable = array();
    /**
     * 数据库查询执行时间数组
     */
    static $dbTable = array();

    /**
     * 缓存查询执行时间数组
     */
    static $cacheTable = array();

    /**
     * 服务调用
     */
    static $serviceTable = array();

    /**
     * 起始时间
     */
    static $beginTime;
    /**
     * debug显示级别
     */
    static $debugLevel;


    /**
     * 启动debug类
     * @param $debug_level 调试级别
     * @param $return 返回所有header为数组（需要手工维护header时，如：非fpm/wcgi环境）
     * @return null
     */
    public static function start($return = false)
    {

        self::$open = true;
        self::$beginTime = microtime();
        self::$timeTable = [];

        self::$cacheTable = [];
        self::$dbTable = [];
        self::$serviceTable = [];
    }

    static function isOpen()
    {
        return self::$open && self::isInFirePHP();
    }

    /**
     * 关闭调试
     * @return bool 返回修改之前的状态值
     */
    public static function stop()
    {
        $old = self::$open;
        self::$open = false;
        return $old;

    }


    /**
     * 获得从起始时间到目前为止所花费的时间
     * @return int
     */
    public static function getTime()
    {
        list($pusec, $psec) = explode(" ", self::$beginTime);
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec - (float)$pusec) + ((float)$sec - (float)$psec);
    }

    /**
     * 返回debug类的实例
     * @return object
     */
    public static function getInstance()
    {

        return self::$instance = self::$instance ?: new self();
    }

    /**
     * 记录用户自定义变量
     * @param string $label 自定义变量显示名称
     * @param mixed $results 自定义变量结果
     * @param string $callfile 调用记录用户自定义变量的文件名
     * @return null
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
            if (is_string($results) && mb_detect_encoding($results, 'UTF-8') !== 'UTF-8') {
                $results = '非UTF-8编码，长度：' . strlen($results);
            }
            array_push(self::$logTable, array($callerAt, $label, $results));
        }
    }


    static function getLogAt($input, $level = 0)
    {
        $delimiter = '/';
        $file = '';

        if (isset($input['file'])) {
            $file = $input['file'];
        } else {
            $file = isset($input['class']) ?$input['class']:'';
        }
        $arr = explode($delimiter, $file);
        $count = count($arr);
        $shortFile = (isset($arr[$count - 2]) ?$arr[$count - 2]: '') . '/' . $arr[$count - 1];
        return "$shortFile:$input[line]";
    }

    /**
     * 记录数据库查询操作执行时间
     * @param $ip
     * @param $database
     * @param $sql
     * @param $times
     * @param $results
     */
    public static function db($times, $sql, $ext)
    {
        if (self::isOpen()) {
            array_push(self::$dbTable, array($times, $sql, $ext));
            /*if (is_string($ip) && strlen($ip) > 24) $ip = substr($ip, 0, 24) . '..';

            if (is_string($results) && strlen($results) > 256) $results = substr($results, 0, 256) . '...(length:' . strlen($results) . ')';
            array_push(self::$dbTable, array($ip, $database, $times, $sql, $results));*/
        }
    }

    /**
     * 记录service调用情况
     * @param $times
     * @param $service
     * @param $method
     * @param $args
     * @param string $cache
     * @param null $results
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
     * @return null
     */
    public static function cache($server, $key, $times, $results, $method = null)
    {
        if (false === self::$open || (defined('DEBUG_SHOW_CACHE') && !DEBUG_SHOW_CACHE)) {
            return;
        }
        if (is_string($results) && strlen($results) > 256) $results = substr($results, 0, 256) . '...(length:' . strlen($results) . ')';
        array_push(self::$cacheTable, array($server, $key, $times, $results, $method));
    }

    /**
     * 记录程序执行时间
     * @param string $desc 描述
     * @param mixed $results 结果
     * @return null
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
     * 判断客户端
     * 修改目的： 1，线上调试更为安全，只有firephp打开而且使用中其面板情况下，才进行调试
     *          2，如果滥用，导致header过大502 Bad Gateway,只会影响打开使用firephp面板的当前session
     * HTTP_USER_AGENT 是以前逻辑，HTTP_X_FIREPHP_VERSION是下面选项逻辑，HTTP_X_WF_PROTOCOL_1调试结果逻辑
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
            FB::table('This Page Spend Times ' . self::getTime(), self::$timeTable);
        }

        if ($count = count(self::$logTable)) {
            array_unshift(self::$logTable, ['file:line', 'Label', 'debug变量结果']);
            FB::table("Custom Log Object $count", self::$logTable);
        }


        // 数据执行时间
        if ($count = count(self::$dbTable)) {
            $totalTimeSpent = array_sum(array_column(self::$dbTable, 0));
            array_unshift(self::$dbTable, array('耗时', 'sql', '所属服务'));
            FB::table($count . ' SQL queries took ' . $totalTimeSpent . ' seconds', self::$dbTable);
        }

        //Cache执行时间
        if (count(self::$cacheTable) > 0) {
            FB::table(self::$cacheTable, self::$cache_total_times);
        }

        // 服务执行时间
        if ($count = count(self::$serviceTable)) {
            $totalTimeSpent = array_sum(array_column(self::$serviceTable, 0));
            array_unshift(self::$serviceTable, array('耗时', 'Service', 'Method', '参数', '命中缓存|事务', 'Results'));
            FB::table("{$count}服务执行{$totalTimeSpent}秒", self::$serviceTable);
        }

    }
}