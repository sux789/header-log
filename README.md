# header-log
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

