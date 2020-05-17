<?php
/**
 * INWX Connector class
 */
namespace inwx;
use core\CONSOLE;

/**
 * Abstract base class for INWX Api classes that connects on construct,
 * sets some session options based on ENV variables and clean disconnects on destruct.
 * @package inwx
 */
abstract class INWXConnector{
    /**
     * Instance of the official Api Class provided by INWX
     * @var \INWX\Domrobot Instance of INWX Api Class
     */
    protected static $api = null;

    /**
     * INWXConnector constructor.
     * Connects to INWX API
     * @throws \ErrorException
     */
    public function __construct()
    {
        if(self::$api === null){
            self::connect();
        }
    }

    /**
     * INWXConnector destructor
     * Disconnects from INWX API at end of execution
     */
    public function __destruct()
    {
        if(self::$api !== null){
            self::$api->logout();
            self::$api = null;
            CONSOLE::info('INWX API Disconnected');
        }

    }

    /**
     * Connect to INWX API
     * @throws \ErrorException
     */
    protected static function connect(){
        CONSOLE::info('Connecting to INWX API');
        $apiTarget = array_key_exists('INWX_SYSTEM', $_ENV) && in_array($_ENV['INWX_SYSTEM'], ['Ote','Live']) ?
            "use".$_ENV['INWX_SYSTEM'] : 'useLive';
        $debug = array_key_exists('INWX_DEBUG', $_ENV) ?
            boolval($_ENV['INWX_DEBUG']) : false;
        $domrobot = new \INWX\Domrobot();
        $result = $domrobot->setLanguage('en')
            // use the OTE endpoint
            // or use the LIVE endpoint instead
            ->$apiTarget()
            // use the JSON-RPC API
            ->useJson()
            // debug will let you see everything you're sending and receiving
            ->setDebug($debug)
            ->login(
                $_ENV['INWX_API_USER'],
                $_ENV['INWX_API_PASS'],
                $_ENV['INWX_API_SECRET']
            );
        if ($result['code'] == 1000) {
            self::$api = $domrobot;
            CONSOLE::success('INWX API Connected');
        } else {
            throw new \ErrorException($result['msg'], $result['code']);
        }
    }
}
