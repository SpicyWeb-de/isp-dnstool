<?php
/**
 * Abstract class DnssecApi
 */
namespace core;

use ErrorException;

/**
 * Class DnssecApi
 * Abstract class to be extended by all provider specific API classes as Entry Points from the CLI logic
 *
 * @package core
 */
abstract class DnssecApi
{
    /**
     * Singleton instance of this class
     * @var DnssecApi Instance of this class for singleton
     */
    private static $_instance;

    /**
     * Create and return the singleton instance of this class
     * @return DnssecApi Instance of singleton
     */
    public static function instance(): DnssecApi{
        if (!self::$_instance)
            self::$_instance = new static();
        return self::$_instance;
    }

    /**
     * Load Keys from Remote API and feed to DNSSecZone Class
     * @return $this
     */
    public abstract function loadRemoteKeys(): self;

    /**
     * Load ISPConfig Keys from exported Keyfile
     * @return $this
     * @throws ErrorException
     */
    public function loadISPKeys(): self{
        CONSOLE::info("Loading Signing Information from ISPConfig export file dnsseckeydata.json");
        $jsonstring = file_get_contents('dnsseckeydata.json');
        $jsondata = json_decode($jsonstring, true);
        if(!!$jsondata){
            foreach($jsondata as $origin => $keys)
                DNSSecZone::addISPConfigKey($origin, $keys);
        } else {
            throw new ErrorException("Could not parse JSON Data from dnsseckeydata.json");
        }
        CONSOLE::success("ISPConfig DNSSEC keys loaded");
        return $this;
    }

    /**
     * Execute Matching of keys from ISPConfig and Remote
     * @return $this
     */
    public function matchListedDomains(): self{
        DNSSecZone::verifyKeys();
        return $this;
    }

    /**
     * Generate a detailed report of all zones and their status in ISPConfig and Remote
     * @return $this
     */
    public function printReport(): self{
        DNSSecZone::printStatusReport();
        return $this;
    }

    /**
     * Generate a summary of domains in ISPConfig and Remote
     * @return $this
     */
    public function printSummary(): self{
        DNSSecZone::printStatusSummary();
        return $this;
    }

    /**
     * Print a list of domains signed by ISPConfig and published in Remote
     * @return $this
     */
    public function printDomainList(): self{
        DNSSecZone::printZoneSystemList();
        return $this;
    }

    /**
     * Print the keys on ISPConfig and Remote for specified zone
     * @param $origin
     * @return $this
     */
    public function printZoneKeys($origin): self{
        DNSSecZone::printZoneKeys($origin);
        return $this;
    }

    /**
     * Publish all unpublished Keys from ISPConfig to remote
     * @return $this
     */
    public abstract function publishUnpublishedKeys(): self;

    /**
     * Remove all keys from Remote servers, that are not known by ISPConfig
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return $this
     */
    public abstract function cleanOrphanedKeys($origin = false): self;

    /**
     * Remove all keys from Remote servers that have a corresponding key in ISPConfig, but in any way different data
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return $this
     */
    public abstract function cleanCorruptedKeys($origin = false): self;
}