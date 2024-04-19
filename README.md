#  composer require sux789/header-log
headers log with firephp

## base use HeaderLog::log($label, $vars)
###  in short 
```php
/**
* header log
* 建议使用 hlog('明确的意义',$var)才更有意义 可以这样hlog($var,$var2,$var3...);
* @author 苏翔
* @param ...$argv
* @return void
  */
function hlog(...$argv)
{

    // handle 1 $label
    $label = 'unnamed';
    // hlog('var_name',$var), hlog('var_name',$var,$var2,$var3...)
    if (count($argv) > 1 && is_string($argv[0]) && strlen($argv[0])) {
        $label = $argv[0];
        array_shift($argv);
    }
    // handle 2 $vars
    foreach ($argv as $item) {
        HeaderLog::log($label, $item, 1);
    }
}
```

## use listen sql,cache etc.
example in tp or lara
```php 
class HeaderLogMiddleware implements MiddlewareInterface
{
    /**
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        // 是否启用头信息调试,如果以后正式环境需要启用，修改这个条件
        $isEnableHeaderLog = env('app_debug');

        $response = null;

        if ($isEnableHeaderLog) {
            ob_start();
            \app\common\HeaderLog::start();
            \think\facade\Db::listen(function ($sql, $time, $explain) {
                if (0 === stripos($sql, 'SHOW') or 0 === stripos($sql, 'CONNECT')) {
                    return false;
                }
                \app\common\HeaderLog::db($time, $sql, $explain);
            });

            $response = $next($request);

            \app\common\HeaderLog::show();
        } else {
            $response = $next($request);
        }

        return $response;
    }
}
```