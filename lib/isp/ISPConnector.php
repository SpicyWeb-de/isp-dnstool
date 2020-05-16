<?php


namespace isp;


use core\CONSOLE;

abstract class ISPConnector
{
    /**
     * @var \GDM\ISPConfig\SoapClient
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

    public function __destruct()
    {
        if(self::$isp){
            self::$isp->logout();
            CONSOLE::info("ISPConfig Remote API Disconnected");
            self::$isp = null;
        }
    }
}