<?php
/**
 * Home of the ISPConnector class
 */
namespace isp;
use core\CONSOLE;

/**
 * Automatically connects to ISPConfig Remote API during construct and terminates the session on destruct
 * @package isp
 */
abstract class ISPConnector
{
    /**
     * @var \GDM\ISPConfig\SoapClient Instance of ISPConfig Remote API Library
     */
    protected static $isp;

    /**
     * ISPConnector constructor. Connects to ISPConfig Remote API
     * @throws \Exception
     */
    public function __construct()
    {
        if (self::$isp === null){
            CONSOLE::info("Connecting to ISPConfig Remote API");
            self::$isp = new \GDM\ISPConfig\SoapClient(
                $_ENV['ISPCONFIG_REMOTE_URI'],
                $_ENV['ISPCONFIG_REMOTE_USER'],
                $_ENV['ISPCONFIG_REMOTE_PASS']
            );
            CONSOLE::success("ISPConfig Remote API connected");
        }
    }

    /**
     * Close the connection to ISPConfig Remote API on destruct
     */
    public function __destruct()
    {
        if(self::$isp){
            self::$isp->logout();
            CONSOLE::info("ISPConfig Remote API Disconnected");
            self::$isp = null;
        }
    }
}