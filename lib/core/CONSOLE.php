<?php
namespace core;
use splitbrain\phpcli\CLI;

abstract class CONSOLE{
    private static $cli;
    public static function registerCli(CLI $cli){
        self::$cli = $cli;
    }
    public static function testout($text){
        self::$cli->info($text);
        self::$cli->notice($text);
        self::$cli->debug($text);
        self::$cli->success($text);
        self::$cli->warning($text);
        self::$cli->alert($text);
        self::$cli->error($text);
        self::$cli->emergency($text);
        self::$cli->critical($text);
        self::$cli->fatal($text);
    }
    public static function __callStatic($name, $arguments)
    {
        if(
            $name == 'success' && $_ENV['VERBOSITY'] < 1
            || $name == 'info' && $_ENV['VERBOSITY'] < 2
            || $name == 'debug' && $_ENV['VERBOSITY'] < 3
        )
            return;
        self::$cli->$name(...$arguments);
    }
}