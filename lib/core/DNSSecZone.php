<?php
/**
 * Home of the DNSSecZone Main Class
 */

namespace core;
use inwx\DNSKeyINWX;
use isp\DNSKeyISP;

/**
 * Main Class for starting actions with the registar API
 *
 * @package core
 */
class DNSSecZone{
    /**
     * List of zones. One instance of this class for each DNS origin.
     * @var DNSSecZone[]
     */
    private static $zones = [];

    /**
     * Add key information from ISPConfig to the zone collection
     * @param string $origin Origin name of the DNS zone
     * @param array $keys = [
     *     'DNSKEY' => DNSKeyISP::$dnskey,
     *     'DS' => DNSKeyISP::$ds
     * ] Keys from ISPConfig
     */
    public static function addISP($origin, $keys){
        if(!array_key_exists($origin, self::$zones))
            self::$zones[$origin] = new self();
        self::$zones[$origin]->_addISP($keys);
    }

    /**
     * Add a key reported from INWX API to the zone collection
     * @param $key = DNSKeyINWX::$keydata Key Information from INWX
     */
    public static function addINWX($key){
        if($key['status'] == 'DELETED' || $key['status'] == 'DELETE')
            return;
        $origin = $key['ownerName'].'.';
        if(!array_key_exists($origin, self::$zones))
            self::$zones[$origin] = new self();
        self::$zones[$origin]->_addINWX($key);
    }

    /**
     * Get a list of all zones with corresponding data in ISPConfig and INWX zones
     * @return DNSSecZone[] Array of Zones
     */
    public static function getLiveZones(){
        return array_filter(self::$zones, function($z){return DNS_KEY_OK === ($z->zonestatus & DNS_KEY_OK);});
    }

    /**
     * Get all zones with keys on INWX servers
     * @return DNSSecZone[] Array of Zones
     */
    public static function getINWXZones(){
        return array_filter(self::$zones, function($z){return $z->hasINWX();});
    }

    /**
     * Get a list of all zones with DNSSEC information in ISPConfig
     * @return DNSSecZone[] Array of Zones
     */
    public static function getISPZones(){
        return array_filter(self::$zones, function($z){return $z->hasISP();});
    }

    /**
     * Get a list of all zones with ISPConfig keys without match in INWX live keys
     * @return DNSSecZone[]
     */
    public static function getZonesWithUnpublishedKeys(){
        return array_filter(self::$zones, function($z){
            // Known in ISP but no full data match in INWX
            // not relevant if there is a corrupt, orphan or no key at all
            // key must be republished anyway
            return DNS_KEY_KNOWN === ($z->zonestatus & DNS_KEY_KNOWN)
                && DNS_KEY_DATA_OK !== ($z->zonestatus & DNS_KEY_DATA_OK);
        });
    }

    /**
     * Get all zones with INWX keys that are known in ISPConfig but differ in details (like digest hash)
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return DNSKeyINWX[]
     */
    public static function getINWXAllCorruptedKeys($origin = false){
        if($origin)
            return self::$zones[$origin]->getINWXCorruptKeys();

        $corruptKeys = [];
        array_walk(self::$zones, function($z) use (&$corruptKeys) {
            $zoneKeys = $z->getINWXCorruptKeys();
            if(count($zoneKeys))
                array_push($corruptKeys, ...$zoneKeys);
        });
        return $corruptKeys;
    }

    /**
     * Get all zones with keys in INWX that are not known in ISPConfig
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return array
     */
    public static function getINWXAllOrphanedKeys($origin = false){
        if($origin)
            return self::$zones[$origin]->getINWXOrphanedKeys();

        $orphanedKeys = [];
        array_walk(self::$zones, function($z) use (&$orphanedKeys) {
            $zoneKeys = $z->getINWXOrphanedKeys();
            if(count($zoneKeys))
                array_push($orphanedKeys, ...$zoneKeys);
        });
        return $orphanedKeys;
    }

    /**
     * Print a list of all zones in ISPConfig and INWIX
     */
    public static function printZoneSystemList(){
        printHeader('ZONE OVERVIEW');
        self::printISPZones();
        self::printINWXZones();
        printf("\n");
    }

    /**
     * Print a detailled status report of all zones and their keys in ISPConfig and INWX
     */
    public static function printStatusReport(){
        printHeader('ZONE STATUS REPORT');
        printf("%-8s %-8s %-8s %-8s %-2s %-2s %s\n",
            'Result', 'ISP', 'INWX', 'Status', 'Co', 'Or', 'Domain'
        );
        foreach(self::$zones as $zone)
            $zone->printStatusReportLine();
        printf("\n");
    }

    /**
     * Print a summary of the status of all zones in ISPConfig and INWX
     */
    public static function printStatusSummary(){
        $liveZones = self::getLiveZones();
        $liveZonesOK = array_filter($liveZones, function($z){return $z->getINWXLiveKey()->getPublishStatus() === 'OK';});
        printHeader("DNSSEC ZONE SUMMARY");
        printf("%-8s Corresponding Keys in ISP and INWX\n", count($liveZones));
        printf("%-8s Corresponding Keys live and working\n", count($liveZonesOK));
        printf("%-8s signed zones in ISPConfig\n", count(self::getISPZones()));
        printf("%-8s DNSSEC key from ISPConfig not published\n", count(self::getZonesWithUnpublishedKeys()));
        printf("%-8s published zones in INWX\n", count(self::getINWXZones()));
        printf("%-8s Keys with corrupt data in INWX\n", count(self::getINWXAllCorruptedKeys()));
        printf("%-8s possible orphan keys in INWX\n", count(self::getINWXAllOrphanedKeys()));
        printf("\n");
    }

    /**
     * Print a list of all zones reported from INWX
     */
    public static function printINWXZones(){
        $inwxZones = self::getINWXZones();
        printSubheader(sprintf("%s DNSSEC Zones published to INWX", count($inwxZones)));
        foreach($inwxZones as $origin => $zone) {
            $zone->printINWXZone();
        }
    }

    /**
     * Print a list of all zones exported from ISPConfig
     */
    public static function printISPZones(){
        $ispZones = self::getISPZones();
        printSubheader(sprintf("%s DNSSEC Zones exported from ISPConfig", count($ispZones)));
        foreach($ispZones as $origin => $zone) {
            $zone->printISP();
        }
    }

    /**
     * Print the string representation of all keys for a specific zone
     * @param $origin string Origin of the zone
     */
    public static function printZoneKeys($origin){
        $ispKey = self::$zones[$origin]->getISPKey();
        $inwxKeys = self::$zones[$origin]->getInwxKeys();
        printHeader($origin." ZONE KEYS");
        printSubheader("ISPConfig Key");
        if($ispKey){
            echo $ispKey->getStringRepresentation()."\n";
        }else{
            CONSOLE::warning("No DNSKey in ISPConfig");
        }
        printSubheader("INWX Keys");
        if($inwxKeys){
            foreach($inwxKeys as $inwxKey)
                echo $inwxKey->getStringRepresentation()."\n";
        }else{
            CONSOLE::warning("No DNSKey in INWX");
        }
    }

    /**
     * Verify the status of all DNSSec Zones and their keys
     */
    public static function verifyKeys(){
        foreach(self::$zones as $zone){
            $zone->verify();
        }
    }




    /**
     * @var string The dns origin zone with trailing period
     */
    private $origin;
    /**
     * @var DNSKeyISP The key that was exported from ISPConfig
     */
    private $ispKey;
    /**
     * @var DNSKeyINWX[] Could be multiple keys per zone as INWX stores all old and deleted keys
     */
    private $inwxKeys;

    /**
     * Information about ISPConfig-/Remotestatus of this zone and the validity of published keys
     * See defined status flags in defines.php
     * @var int
     */
    private $zonestatus = DNS_KEY_NOT_CHECKED;

    /**
     * Add the DNSSec key from ISPConfig to this zone
     * @param $key = [
     *     'DNSKEY' => DNSKeyISP::$dnskey,
     *     'DS' => DNSKeyISP::$ds
     * ] Key from ISPConfig
     * @return $this
     */
    protected function _addISP($key)
    {
        $this->ispKey = new DNSKeyISP($key);
        $this->origin = $key['DNSKEY']['origin'];
        return $this;
    }

    /**
     * Add a key reported from INWX to the remote key collection of this zone
     * @param $key = DNSKeyINWX::$keydata Key Information from INWX
     * @return $this
     */
    protected function _addINWX($key)
    {
        $this->inwxKeys[] = new DNSKeyINWX($key);
        $this->origin = $key['ownerName'].'.';
        return $this;
    }

    /**
     * Test if this zone has a key provided by ISPConfig
     * @return bool Has ISPConfig key
     */
    protected function hasISP(){
        return $this->ispKey instanceof DNSKeyISP;
    }

    /**
     * Test if this zone has at least one active key on the remote server
     * @return bool Has remote key
     */
    protected function hasINWX(){
        return !!count($this->inwxKeys);
    }

    /**
     * Print a line with the zone name of the ISPConfig key
     * @return $this
     */
    protected function printISP(){
        if($this->hasISP()) {
            printf("     - %s\n", $this->ispKey);
        }
        return $this;
    }

    /**
     * Print a line with the zone name of the remote key
     * @return $this
     */
    protected function printINWXZone(){
        if($this->hasINWX()) {
            printf("     - %s\n", $this->inwxKeys[0]);
        }
        return $this;
    }

    /**
     * Get all keys listed on remote server
     * @return DNSKeyINWX[]|null
     */
    public function getINWXKeys(){
        if(!$this->hasINWX())
            return null;
        return $this->inwxKeys;
    }

    /**
     * Get the active remote key object that corresponds to the key provided by ISPConfig (if any)
     * @return mixed|null
     */
    public function getINWXLiveKey(){
        if(!$this->hasINWX())
            return null;
        $liveKeys = array_filter($this->inwxKeys, function($key){return DNS_KEY_OK === ($key->getKeyStatus() & DNS_KEY_OK);});
        return array_shift($liveKeys);
    }

    /**
     * Get the DNSSec key provided by ISPConfig
     * @return DNSKeyISP|null
     */
    public function getISPKey(){
        if(!$this->hasINWX())
            return null;
        return $this->ispKey;
    }

    /**
     * Get remote keys of this zone, that match the known public key from ISPConfig but differ in details
     * @return DNSKeyINWX[]|null
     */
    public function getINWXCorruptKeys(){
        if(!$this->hasINWX())
            return null;
        // Key is known from ISP, but data is not valid
        return array_filter($this->inwxKeys, function($key){
            return $key->getKeyStatus() == DNS_KEY_KNOWN_AND_PUBLISHED;
        });
    }

    /**
     * Get all remote keys that have an unknown public key (not from ISPConfig)
     * @return array|DNSKeyINWX[]
     */
    public function getINWXOrphanedKeys(){
        if(!$this->hasINWX())
            return [];
        // Only published, not known by ISP and not data valid
        return array_filter($this->inwxKeys, function($key){return $key->getKeyStatus() == DNS_KEY_PUBLISHED;});
    }


    /*
     * ISP  INWX    Domain
     * check  check
     * check clock
     * times check
     * check times
     * check clock 5t (totenkreuz-> orphaned)
     */
    /**
     * Print the status report line for this zone containing overall zone status, key existence
     * in ISPConfig and remote and counts of corrupt and orphaned keys
     */
    protected function printStatusReportLine(){
        $statusOverall = (DNS_KEY_OK == ($this->zonestatus & DNS_KEY_OK)) ? 'x' : '-';
        $statusISP = (DNS_KEY_KNOWN == ($this->zonestatus & DNS_KEY_KNOWN)) ? 'x' : '-';
        $statusINWX = (DNS_KEY_PUBLISHED == ($this->zonestatus & DNS_KEY_PUBLISHED)) ? 'x' : '-';
        $liveKey = $this->getINWXLiveKey();
        $statusLiveKey = $liveKey ? $liveKey->getPublishStatus() : '';

        $countCorrupt = count($this->getINWXCorruptKeys());
        $corrupts = $countCorrupt ? $countCorrupt : ''; // Corrupts
        $countOrphans = count($this->getINWXOrphanedKeys());
        $orphans = $countOrphans ? $countOrphans : '';

        printf("%-8s %-8s %-8s %-8s %-2s %-2s %s\n",
            $statusOverall,
            $statusISP,
            $statusINWX,
            $statusLiveKey,
            $corrupts,
            $orphans,
            $this->origin
        );
    }

    /**
     * Verify the existence of this zone at ISPConfig and remote and match the keys to probe
     * for a working remote key that matches the actual signing keys from ISPCofnig
     * @return $this
     */
    protected function verify(){
        if($this->hasISP()){
            $this->zonestatus |= DNS_KEY_KNOWN;
        }
        if($this->hasINWX()){
            $this->zonestatus |= DNS_KEY_PUBLISHED;
        }
        if($this->zonestatus != DNS_KEY_KNOWN_AND_PUBLISHED)
            return $this;
        // If zone is known and published, compare keys
        // Find the matching one and flag orphaned
        foreach($this->inwxKeys as $inwxKey){
            $inwxKey->match($this->ispKey);
            // Add keystatus to zonestatus. Becomes DATA_OK if key is
            $this->zonestatus |= $inwxKey->getKeyStatus();
        }
        return $this;
    }
}