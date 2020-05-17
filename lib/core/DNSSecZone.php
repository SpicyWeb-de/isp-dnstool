<?php
/**
 * Home of the DNSSecZone Main Class
 */

namespace core;
use inwx\DnssecRecordInwx;
use inwx\RemoteDnssecRecord;
use isp\DnssecRecordISP;

/**
 * Main Class for starting actions with the Remote registar API
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
     * @param array $key = [
     *     'DNSKEY' => DNSKeyISP::$dnskey,
     *     'DS' => DNSKeyISP::$ds
     * ] Keys from ISPConfig
     */
    public static function addISPConfigKey($origin, $key){
        if(!array_key_exists($origin, self::$zones))
            self::$zones[$origin] = new self();
        self::$zones[$origin]->_addISPConfigKey($key);
    }

    /**
     * Add a key reported from Remote API to the zone collection
     * @param $key = RemoteDnssecRecord::$keydata Key Information from DNS registrar
     */
    public static function addRemoteKey($key){
        if($key['status'] == 'DELETED' || $key['status'] == 'DELETE')
            return;
        $origin = $key['ownerName'].'.';
        if(!array_key_exists($origin, self::$zones))
            self::$zones[$origin] = new self();
        self::$zones[$origin]->_addRemoteKey($key);
    }

    /**
     * Get a list of all zones with corresponding data in ISPConfig and Remote zones
     * @return DNSSecZone[] Array of Zones
     */
    public static function getLiveZones(){
        return array_filter(self::$zones, function($z){return DNS_KEY_OK === ($z->zonestatus & DNS_KEY_OK);});
    }

    /**
     * Get all zones with keys published on Remote servers
     * @return DNSSecZone[] Array of Zones
     */
    public static function getRemoteZones(){
        return array_filter(self::$zones, function($z){return $z->hasRemoteKey();});
    }

    /**
     * Get a list of all zones with DNSSEC information in ISPConfig
     * @return DNSSecZone[] Array of Zones
     */
    public static function getISPZones(){
        return array_filter(self::$zones, function($z){return $z->hasISPConfigKey();});
    }

    /**
     * Get a list of all zones with ISPConfig keys without match in Remote live keys
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
     * Get all zones with Remote keys that are known in ISPConfig but differ in details (like digest hash)
     * @param string|false $origin Optional: Origin name to delete instead of all
     * @return DnssecRecordRemote[]
     */
    public static function getCorruptedKeys($origin = false){
        if($origin)
            return self::$zones[$origin]->getRemoteCorruptedKeys();

        $corruptKeys = [];
        array_walk(self::$zones, function($z) use (&$corruptKeys) {
            $zoneKeys = $z->getRemoteCorruptedKeys();
            if(count($zoneKeys))
                array_push($corruptKeys, ...$zoneKeys);
        });
        return $corruptKeys;
    }

    /**
     * Get all zones with keys on Remote server that are not known in ISPConfig
     * @param string|false $origin Optional: Origin name to get the keys for
     * @return array
     */
    public static function getOrphanedKeys($origin = false){
        if($origin)
            return self::$zones[$origin]->getRemoteOrphanedKeys();

        $orphanedKeys = [];
        array_walk(self::$zones, function($z) use (&$orphanedKeys) {
            $zoneKeys = $z->getRemoteOrphanedKeys();
            if(count($zoneKeys))
                array_push($orphanedKeys, ...$zoneKeys);
        });
        return $orphanedKeys;
    }

    /**
     * Print a list of all zones in ISPConfig and on Remote server
     */
    public static function printZoneSystemList(){
        printHeader('ZONE OVERVIEW');
        self::printISPZones();
        self::printRemoteZones();
        printf("\n");
    }

    /**
     * Print a detailled status report of all zones and their keys in ISPConfig and Remote
     */
    public static function printStatusReport(){
        printHeader('ZONE STATUS REPORT');
        printf("%-8s %-8s %-8s %-8s %-2s %-2s %s\n",
            'Result', 'ISP', 'Registry', 'Status', 'Co', 'Or', 'Domain'
        );
        foreach(self::$zones as $zone)
            $zone->printStatusReportLine();
        printf("\n");
    }

    /**
     * Print a summary of the status of all zones in ISPConfig and Remote
     */
    public static function printStatusSummary(){
        $liveZones = self::getLiveZones();
        $liveZonesOK = array_filter($liveZones, function($z){return $z->getRemoteLiveKey()->getPublishStatus() === 'OK';});
        printHeader("DNSSEC ZONE SUMMARY");
        printf("%-8s Corresponding Keys in ISP and Remote\n", count($liveZones));
        printf("%-8s Corresponding Keys live and working\n", count($liveZonesOK));
        printf("%-8s signed zones in ISPConfig\n", count(self::getISPZones()));
        printf("%-8s DNSSEC key from ISPConfig not published\n", count(self::getZonesWithUnpublishedKeys()));
        printf("%-8s Remote published zones\n", count(self::getRemoteZones()));
        printf("%-8s Remote Keys with corrupt data\n", count(self::getCorruptedKeys()));
        printf("%-8s possible Remote orphan keys\n", count(self::getOrphanedKeys()));
        printf("\n");
    }

    /**
     * Print a list of all zones reported from Remote
     */
    public static function printRemoteZones(){
        $remoteZones = self::getRemoteZones();
        printSubheader(sprintf("%s DNSSEC Zones published Remote", count($remoteZones)));
        foreach($remoteZones as $origin => $zone) {
            $zone->printRemoteZone();
        }
    }

    /**
     * Print a list of all zones exported from ISPConfig
     */
    public static function printISPZones(){
        $ispZones = self::getISPZones();
        printSubheader(sprintf("%s DNSSEC Zones exported from ISPConfig", count($ispZones)));
        foreach($ispZones as $origin => $zone) {
            $zone->printISPConfigZone();
        }
    }

    /**
     * Print the string representation of all keys for a specific zone
     * @param $origin string Origin of the zone
     */
    public static function printZoneKeys($origin){
        $ispKey = self::$zones[$origin]->getISPConfigKey();
        $remoteKeys = self::$zones[$origin]->getRemoteKeys();
        printHeader($origin." ZONE KEYS");
        printSubheader("ISPConfig Key");
        if($ispKey){
            echo $ispKey->getStringRepresentation()."\n";
        }else{
            CONSOLE::warning("No DNSKey in ISPConfig");
        }
        printSubheader("Remote Keys");
        if($remoteKeys){
            foreach($remoteKeys as $remoteKey)
                echo $remoteKey->getStringRepresentation()."\n";
        }else{
            CONSOLE::warning("No Remote published DNSKey");
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
     * @var DnssecRecordISP The key that was exported from ISPConfig
     */
    private $ispconfigKey;
    /**
     * @var DnssecRecordRemote[] Could be multiple keys per zone as INWX stores all old and deleted keys
     */
    private $remoteKeys;

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
    public function _addISPConfigKey($key)
    {
        $this->ispconfigKey = new DnssecRecordISP($key);
        $this->origin = $key['DNSKEY']['origin'];
        return $this;
    }

    /**
     * Add a key reported from INWX to the remote key collection of this zone
     * @param $key = DnssecRecordInwx::$keydata Key Information from INWX
     * @return $this
     */
    public function _addRemoteKey($key)
    {
        $this->remoteKeys[] = new DnssecRecordInwx($key);
        $this->origin = $key['ownerName'].'.';
        return $this;
    }

    /**
     * Test if this zone has a key provided by ISPConfig
     * @return bool Has ISPConfig key
     */
    public function hasISPConfigKey(){
        return $this->ispconfigKey instanceof DnssecRecordISP;
    }

    /**
     * Test if this zone has at least one active key on the remote server
     * @return bool Has remote key
     */
    public function hasRemoteKey(){
        return !!count($this->remoteKeys);
    }

    /**
     * Print a line with the zone name of the ISPConfig key
     * @return $this
     */
    public function printISPConfigZone(){
        if($this->hasISPConfigKey()) {
            printf("     - %s\n", $this->ispconfigKey);
        }
        return $this;
    }

    /**
     * Print a line with the zone name of the remote key
     * @return $this
     */
    public function printRemoteZone(){
        if($this->hasRemoteKey()) {
            printf("     - %s\n", $this->remoteKeys[0]);
        }
        return $this;
    }

    /**
     * Get all keys listed on remote server
     * @return DnssecRecordRemote[]|null
     */
    public function getRemoteKeys(){
        if(!$this->hasRemoteKey())
            return null;
        return $this->remoteKeys;
    }

    /**
     * Get the active remote key object that corresponds to the key provided by ISPConfig (if any)
     * @return mixed|null
     */
    public function getRemoteLiveKey(){
        if(!$this->hasRemoteKey())
            return null;
        $liveKeys = array_filter($this->remoteKeys, function($key){return DNS_KEY_OK === ($key->getKeyStatus() & DNS_KEY_OK);});
        return array_shift($liveKeys);
    }

    /**
     * Get the DNSSec key provided by ISPConfig
     * @return DnssecRecordISP|null
     */
    public function getISPConfigKey(){
        if(!$this->hasRemoteKey())
            return null;
        return $this->ispconfigKey;
    }

    /**
     * Get remote keys of this zone, that match the known public key from ISPConfig but differ in details
     * @return DnssecRecordRemote[]|null
     */
    public function getRemoteCorruptedKeys(){
        if(!$this->hasRemoteKey())
            return null;
        // Key is known from ISP, but data is not valid
        return array_filter($this->remoteKeys, function($key){
            return $key->getKeyStatus() == DNS_KEY_KNOWN_AND_PUBLISHED;
        });
    }

    /**
     * Get all remote keys that have an unknown public key (not from ISPConfig)
     * @return array|DnssecRecordRemote[]
     */
    public function getRemoteOrphanedKeys(){
        if(!$this->hasRemoteKey())
            return [];
        // Only published, not known by ISP and not data valid
        return array_filter($this->remoteKeys, function($key){return $key->getKeyStatus() == DNS_KEY_PUBLISHED;});
    }

    /**
     * Print the status report line for this zone containing overall zone status, key existence
     * in ISPConfig and remote and counts of corrupt and orphaned keys
     */
    public function printStatusReportLine(){
        $statusOverall = (DNS_KEY_OK == ($this->zonestatus & DNS_KEY_OK)) ? 'x' : '-';
        $statusISP = (DNS_KEY_KNOWN == ($this->zonestatus & DNS_KEY_KNOWN)) ? 'x' : '-';
        $statusRemote = (DNS_KEY_PUBLISHED == ($this->zonestatus & DNS_KEY_PUBLISHED)) ? 'x' : '-';
        $liveKey = $this->getRemoteLiveKey();
        $statusLiveKey = $liveKey ? $liveKey->getPublishStatus() : '';

        $countCorrupt = count($this->getRemoteCorruptedKeys());
        $corrupts = $countCorrupt ? $countCorrupt : ''; // Corrupts
        $countOrphans = count($this->getRemoteOrphanedKeys());
        $orphans = $countOrphans ? $countOrphans : '';

        printf("%-8s %-8s %-8s %-8s %-2s %-2s %s\n",
            $statusOverall,
            $statusISP,
            $statusRemote,
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
    public function verify(){
        if($this->hasISPConfigKey()){
            $this->zonestatus |= DNS_KEY_KNOWN;
        }
        if($this->hasRemoteKey()){
            $this->zonestatus |= DNS_KEY_PUBLISHED;
        }
        if($this->zonestatus != DNS_KEY_KNOWN_AND_PUBLISHED)
            return $this;
        // If zone is known and published, compare keys
        // Find the matching one and flag orphaned
        foreach($this->remoteKeys as $remoteKey){
            $remoteKey->match($this->ispconfigKey);
            // Add keystatus to zonestatus. Becomes DATA_OK if key is
            $this->zonestatus |= $remoteKey->getKeyStatus();
        }
        return $this;
    }

    /**
     * Get the status of this zone
     * @return int
     */
    public function getZoneStatus(): int {
        return $this->zonestatus;
    }


}