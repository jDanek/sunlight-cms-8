<?php

use Sunlight\Core;
use Kuria\Debug\Dumper;

if (!function_exists('dump')) {
    function dump($value, $maxLevel = Dumper::DEFAULT_MAX_LEVEL, $maxStringLen = Dumper::DEFAULT_MAX_STRING_LENGTH)
    {
        if (PHP_VERSION_ID >= 50306) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        } else {
            $trace = debug_backtrace(false);
        }

        if (isset($trace[0]['file'], $trace[0]['line'])) {
            $file = $trace[0]['file'];
            $line = $trace[0]['line'];
        } else {
            $file = 'unknown';
            $line = 0;
        }

        Core::$pluginManager->getExtend('devkit')->addDump(
            $file,
            $line,
            Dumper::dump($value, $maxLevel, $maxStringLen)
        );
    }

    if (!function_exists('dd')) {
        function dd($value, $maxLevel = Dumper::DEFAULT_MAX_LEVEL, $maxStringLen = Dumper::DEFAULT_MAX_STRING_LENGTH)
        {
            echo '<pre>', _e(Dumper::dump($value, $maxLevel, $maxStringLen)), '</pre>';
            exit(1);
        }
    }
}
