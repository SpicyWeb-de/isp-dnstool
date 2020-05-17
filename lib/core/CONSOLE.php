<?php
/**
 * Small abstraction of the CLI apps output functions
 * This way, we dont have to pass the CLI app instance to each method to provide output capabilities,
 * we can just call a method from this static class.
 */
namespace core;
use splitbrain\phpcli\CLI;

/**
 * Class CONSOLE
 * Static abstraction for global access to the CLI output capabilities
 * @package core
 */
abstract class CONSOLE{
    /**
     * Instance of the CLI app
     * @var CLI CLI app
     */
    private static $cli;

    /**
     * Register the CLI app for further use
     * @param CLI $cli
     */
    public static function registerCli(CLI $cli){
        self::$cli = $cli;
    }

    /**
     * Just a test method to use all available output methods and see their different style
     * @param $text
     */
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

    /**
     * Abstraction to make all CLI methods callable from within the entire application by using this static class
     * @param $name
     * @param $arguments
     */
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