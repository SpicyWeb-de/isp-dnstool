<?php
/**
 * INWX DNSSec Api Class
 */
namespace inwx;

use core\CONSOLE;
use core\DNSSecZone;

/**
 * Class INWXDNSSecApi
 * Autoconnects to INWX Api and provides several operations to work with the API Data
 * @package inwx
 */
class INWXDNSSecApi extends INWXConnector {
    /**
     * Singleton instance of this class
     * @var INWXDNSSecApi Instance of this class for singleton
     */
    private static $_instance;

    /**
     * Create and return the singleton instance of this class
     * @return INWXDNSSecApi Instance of singleton
     */
    public static function instance(): INWXDNSSecApi
    {
        if (!self::$_instance)
            self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * Load INWX Keys from API and feed to DNSSecZone
     * @return $this
     */
    public function loadPublishedKeys(){
        $result = self::$api->call('dnssec', 'listkeys');
        if($result['code'] == 1000){
            foreach($result['resData'] as $inwxKey)
                DNSSecZone::addINWX($inwxKey);
        } else {
            throw new \Error($result['msg'], $result['code']);
        }
        return $this;
    }

    /**
     * Load ISPConfig Keys from exported Keyfile
     * @return $this
     * @throws \ErrorException
     */
    public function loadISPKeys(){
        CONSOLE::info("Loading Signing Information from ISPConfig export file dnsseckeydata.json");
        $jsonstring = file_get_contents('dnsseckeydata.json');
        $jsondata = json_decode($jsonstring, true);
        if(!!$jsondata){
            foreach($jsondata as $origin => $keys)
                DNSSecZone::addISP($origin, $keys);
        } else {
            throw new \ErrorException("Could not parse JSON Data from dnsseckeydata.json");
        }
        CONSOLE::success("ISPConfig DNSSEC keys loaded");
        return $this;
    }

    /**
     * Execute Matching of keys from ISPConfig and INWX
     * @return $this
     */
    public function matchListedDomains(){
        DNSSecZone::verifyKeys();
        return $this;
    }

    /**
     * Generate a detailed report of all zones and their status in ISPConfig and INWX
     * @return $this
     */
    public function printReport(){
        DNSSecZone::printStatusReport();
        return $this;
    }

    /**
     * Generate a summary of domains in ISPConfig and INWX
     * @return $this
     */
    public function printSummary(){
        DNSSecZone::printStatusSummary();
        return $this;
    }

    /**
     * Print a list of domains signed by ISPConfig and published in INWX
     * @return $this
     */
    public function printDomainList(){
        DNSSecZone::printZoneSystemList();
        return $this;
    }

    /**
     * Print the keys on ISPConfig and INWX for specified zone
     * @param $origin
     */
    public function printZoneKeys($origin){
        DNSSecZone::printZoneKeys($origin);
    }

    /**
     * Publish all unpublished Keys from ISPConfig to INWX
     * @return $this
     */
    public function publishAllUnpublishedKeys(){
        printHeader("PUBLISHING ALL UNPUBLISHED KEYS");
        $keys = DNSSecZone::getZonesWithUnpublishedKeys();
        foreach($keys as $key){
            $ispKey = $key->getISPKey();
            $params = [
                'domainName' => $ispKey->getFqdn(),
                'dnskey' => $ispKey->getDNSKEYRecord(),
                'ds' => $ispKey->getDSRecord(),
                'calculateDigest' => false,
            ];
            $this->performKeyOperation('adddnskey', $params, $ispKey);
        }
        printf("\n");
        return $this;
    }

    /**
     * Remove all keys from INWX servers, that are not known by ISPConfig
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return $this
     */
    public function cleanOrphanedKeys($origin = false){
        printHeader("REMOVING ALL ORPHANED KEYS");
        $keys = DNSSecZone::getINWXAllOrphanedKeys($origin);
        foreach($keys as $key){
            $params = [
                'key' => $key->getKeyID()
            ];
            $this->performKeyOperation('deletednskey', $params, $key);
        }
        printf("\n");
        return $this;
    }

    /**
     * Remove all keys from INWX servers that have a corresponding key in ISPConfig, but in any way different data
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return $this
     */
    public function cleanCorruptedKeys($origin = false){
        printHeader("REMOVING ALL ENTRIES WITH CORRUPTED KEY DATA");
        $keys = DNSSecZone::getINWXAllCorruptedKeys($origin);
        foreach($keys as $key){
            $params = [
                'key' => $key->getKeyID()
            ];
            $this->performKeyOperation('deletednskey', $params, $key);
        }
        printf("\n");
        return $this;
    }

    /**
     * Perform a DNSSEC operation for a key at INWX API and print the result
     * @param $method Method to call
     * @param $params Params as required by the API function
     * @param $key The key object (just for name-printing)
     * @return $this
     */
    private function performKeyOperation($method, $params, $key) {
        $result = self::$api->call('dnssec', $method, $params);
        if($result['code'] == '1000'){
            printf("%-8s %s\n", 'OK', $key);
        }else{
            printf("%-8s %s\n", 'ERROR', $key);
            printf("%-8s [%s] %s\n", '', $result['code'], $result['msg']);
        }
        return $this;
    }
}