<?php
/**
 * INWX DNSSec Api Class
 */
namespace inwx;

use core\DNSSEC;
use core\DnssecApi;
use core\DNSSecZone;

/**
 * Class DnssecApiInwx
 * Autoconnects to INWX Api and provides several operations to work with the API Data
 * @package inwx
 */
class DnssecApiInwx extends DnssecApi  {
    use INWXConnector;

    /**
     * Load INWX Keys from API and feed to DNSSecZone
     * @return $this
     */
    public function loadRemoteKeys(): DnssecApi {
        $result = self::$api->call('dnssec', 'listkeys');
        if($result['code'] == 1000){
            foreach($result['resData'] as $inwxKey)
                DNSSecZone::addRemoteKey($inwxKey);
        } else {
            throw new \Error($result['msg'], $result['code']);
        }
        return $this;
    }

    /**
     * Publish all unpublished Keys from ISPConfig to INWX
     * @return $this
     */
    public function publishUnpublishedKeys(): self {
        printHeader("PUBLISHING ALL UNPUBLISHED KEYS");
        $keys = DNSSecZone::getZonesWithUnpublishedKeys();
        foreach($keys as $key){
            $ispKey = $key->getISPConfigKey();
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
    public function cleanOrphanedKeys($origin = false): DnssecApi{
        printHeader("REMOVING ALL ORPHANED KEYS");
        $keys = DNSSecZone::getOrphanedKeys($origin);
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
    public function cleanCorruptedKeys($origin = false): DnssecApi{
        printHeader("REMOVING ALL ENTRIES WITH CORRUPTED KEY DATA");
        $keys = DNSSecZone::getCorruptedKeys($origin);
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