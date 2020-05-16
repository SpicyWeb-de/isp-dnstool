<?php


namespace core;


use inwx\DNSKeyINWX;
use isp\DNSKeyISP;

class DNSSecZone{
    /**
     * @var DNSSecZone[]
     */
    private static $zones = [];
    public static function addISP($origin, $keys){
        if(!array_key_exists($origin, self::$zones))
            self::$zones[$origin] = new self();
        self::$zones[$origin]->_addISP($keys);
    }
    public static function addINWX($key){
        if($key['status'] == 'DELETED' || $key['status'] == 'DELETE')
            return;
        $origin = $key['ownerName'].'.';
        if(!array_key_exists($origin, self::$zones))
            self::$zones[$origin] = new self();
        self::$zones[$origin]->_addINWX($key);
    }

    public static function getLiveZones(){
        return array_filter(self::$zones, function($z){return DNS_KEY_OK === ($z->zonestatus & DNS_KEY_OK);});
    }

    public static function getINWXZones(){
        return array_filter(self::$zones, function($z){return $z->hasINWX();});
    }
    public static function getISPZones(){
        return array_filter(self::$zones, function($z){return $z->hasISP();});
    }

    public static function getUnpublishedKeys(){
        return array_filter(self::$zones, function($z){
            // Known in ISP but no full data match in INWX
            // not relevant if there is a corrupt, orphan or no key at all
            // key must be republished anyway
            return DNS_KEY_KNOWN === ($z->zonestatus & DNS_KEY_KNOWN)
                && DNS_KEY_DATA_OK !== ($z->zonestatus & DNS_KEY_DATA_OK);
        });
    }

    /**
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

    public static function printZoneSystemList(){
        printHeader('ZONE OVERVIEW');
        self::printISPZones();
        self::printINWXZones();
        printf("\n");
    }

    public static function printStatusReport(){
        printHeader('ZONE STATUS REPORT');
        printf("%-8s %-8s %-8s %-8s %-2s %-2s %s\n",
            'Result', 'ISP', 'INWX', 'Status', 'Co', 'Or', 'Domain'
        );
        foreach(self::$zones as $zone)
            $zone->printStatusReportLine();
        printf("\n");
    }

    public static function printStatusSummary(){
        $liveZones = self::getLiveZones();
        $liveZonesOK = array_filter($liveZones, function($z){return $z->getINWXLiveKey()->getPublishStatus() === 'OK';});
        printHeader("DNSSEC ZONE SUMMARY");
        printf("%-8s Corresponding Keys in ISP and INWX\n", count($liveZones));
        printf("%-8s Corresponding Keys live and working\n", count($liveZonesOK));
        printf("%-8s signed zones in ISPConfig\n", count(self::getISPZones()));
        printf("%-8s DNSSEC key from ISPConfig not published\n", count(self::getUnpublishedKeys()));
        printf("%-8s published zones in INWX\n", count(self::getINWXZones()));
        printf("%-8s Keys with corrupt data in INWX\n", count(self::getINWXAllCorruptedKeys()));
        printf("%-8s possible orphan keys in INWX\n", count(self::getINWXAllOrphanedKeys()));
        printf("\n");
    }

    public static function printINWXZones(){
        $inwxZones = self::getINWXZones();
        printSubheader(sprintf("%s DNSSEC Zones published to INWX", count($inwxZones)));
        foreach($inwxZones as $origin => $zone) {
            $zone->printINWXZone();
        }
    }
    public static function printISPZones(){
        $ispZones = self::getISPZones();
        printSubheader(sprintf("%s DNSSEC Zones exported from ISPConfig", count($ispZones)));
        foreach($ispZones as $origin => $zone) {
            $zone->printISP();
        }
    }

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
     * @var DNSKeyISP
     */
    private $ispKey;
    /**
     * @var DNSKeyINWX[] Could be multiple keys per zone as INWX stores all old and deleted keys
     */
    private $inwxKeys;
    private $zonestatus = DNS_KEY_NOT_CHECKED;
    protected function _addISP($keys)
    {
        $this->ispKey = new DNSKeyISP($keys);
        $this->origin = $keys['DNSKEY']['origin'];
        return $this;
    }
    protected function _addINWX($key)
    {
        $this->inwxKeys[] = new DNSKeyINWX($key);
        $this->origin = $key['ownerName'].'.';
        return $this;
    }
    protected function hasISP(){
        return $this->ispKey instanceof DNSKeyISP;
    }
    protected function hasINWX(){
        return !!count($this->inwxKeys);
    }
    protected function printISP(){
        if($this->hasISP()) {
            printf("     - %s\n", $this->ispKey);
        }
        return $this;
    }
    protected function printINWXZone(){
        if($this->hasINWX()) {
            printf("     - %s\n", $this->inwxKeys[0]);
        }
        return $this;
    }

    public function getINWXKeys(){
        if(!$this->hasINWX())
            return null;
        return $this->inwxKeys;
    }

    public function getINWXLiveKey(){
        if(!$this->hasINWX())
            return null;
        $liveKeys = array_filter($this->inwxKeys, function($key){return DNS_KEY_OK === ($key->getKeyStatus() & DNS_KEY_OK);});
        return array_shift($liveKeys);
    }

    public function getISPKey(){
        if(!$this->hasINWX())
            return null;
        return $this->ispKey;
    }

    public function getINWXCorruptKeys(){
        if(!$this->hasINWX())
            return null;
        // Key is known from ISP, but data is not valid
        return array_filter($this->inwxKeys, function($key){
            return $key->getKeyStatus() == DNS_KEY_KNOWN_AND_PUBLISHED;
        });
    }

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