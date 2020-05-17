<?php
/**
 * ISPDnssec Class
 */
namespace isp;
use core\CONSOLE;

/**
 * Several operation with DNSSec information from ISPConfig DNS Zones
 * @package isp
 */
class ISPDnssec
{
    use ISPConnector;
    /**
     * @var ISPDnssec Singleton instance
     */
    private static $_instance;

    /**
     * @var array = [[
     *     'customer' => ISPClientApi::getAll()[0],
     *     'zone' => ISPDnsApi::getZone(),
     *     'dnssec' => ISPDnssec::parseDnssecInfo()
     * ]]
     */
    private $cachedZoneData;

    /**
     * Get singleton instance for this class
     * @return ISPDnssec Singleton instance
     */
    public static function instance()
    {
        if (!self::$_instance)
            self::$_instance = new self();
        return self::$_instance;
    } // cache keys after retrieving the first time

    /**
     * ISPDnssec constructor.
     * @throws \Exception
     */
    function __construct()
    {
        printHeader("ISPCONFIG DNSSEC EXPORTER");
        parent::__construct();
    }

    /**
     * Load all DNSSec Keys from ISPConfig
     * @return $this
     */
    public function loadKeys()
    {
        // Get IDs of all DNS servers
        $dnsservers = ISPDnsApi::instance()->getAllServerIds();
        // Loop all clients to get their domains
        $dnssecdata = [];
        if (!$this->cachedZoneData) {
            CONSOLE::info("Loading DNS-Zones of all clients");
            foreach (ISPClientApi::instance()->getAll() as $client) {
                $cid = $client['client_id'];
                foreach ($dnsservers as $sid) {
                    $zones = self::$isp->dnsZoneGetByUser($cid, $sid);
                    foreach ($zones as $z) {
                        $zone = ISPDnsApi::instance()->getZone($z['id']);

                        $dnssecdata[$z['origin']] = [
                            'customer' => $client,
                            'zone' => $zone,
                            'dnssec' => $this->parseDnssecInfo($zone),
                        ];
                    }
                }
                printf(".");
            }
            printf("\n");
            $this->cachedZoneData = $dnssecdata;
        } else {
            CONSOLE::info("Using cached DNS-Zones");
        }
        CONSOLE::success(sprintf("%s Zones loaded", count($this->cachedZoneData)));
        return $this;
    }

    /**
     * Parse DNSSec information of a zone and split it into contained parts
     * @param $zone ISPDnsApi::getZone()
     * @return array[]|bool
     */
    private function parseDnssecInfo($zone)
    {
        if ($zone['dnssec_initialized'] === 'N')
            return false;
        $dnssecdata = $zone['dnssec_info'];
        // [2-] position of hash algorithm. 1 is sha1. no one wants sha one!
        preg_match_all('/(\S*)\s+IN DS (\d+) (\d) ([2-]) ([\S ]+)/', $dnssecdata, $dsMatch);
        preg_match_all('/(\S*)\s+IN DNSKEY 256 (\d) (\d) ([\S ]+)/', $dnssecdata, $zskMatch);
        preg_match_all('/(\S*)\s+IN DNSKEY 257 (\d) (\d) ([\S ]+)/', $dnssecdata, $kskMatch);

        $ds = [
            'record'   => $dsMatch[0][0],
            'origin'   => $dsMatch[1][0],
            'id'       => $dsMatch[2][0],
            'cipher'   => $dsMatch[3][0],
            'hashtype' => $dsMatch[4][0],
            'hash'     => join('', explode(' ', $dsMatch[5][0])),
        ];
        $zsk = [
            'record'   => $zskMatch[0][0],
            'origin'   => $zskMatch[1][0],
            'type'     => '256',
            'protocol' => $zskMatch[2][0],
            'cipher'   => $zskMatch[3][0],
            'key'      => join('', explode(' ', $zskMatch[4][0])),
        ];
        $ksk = [
            'record'   => $kskMatch[0][0],
            'origin'   => $kskMatch[1][0],
            'type'     => '257',
            'protocol' => $kskMatch[2][0],
            'cipher'   => $kskMatch[3][0],
            'key'      => join('', explode(' ', $kskMatch[4][0])),
        ];

        return [
            'DS' => $ds,
            'ZSK' => $zsk,
            'KSK' => $ksk,
        ];
    }

    /**
     * Export DNSSec keys from ISPConfig to a local json file
     * @return $this
     */
    public function exportKeys()
    {
        CONSOLE::info("Exporting DNSSec data of loaded zones for submission to registrar");
        CONSOLE::info("Target file: dnsseckeydata.json");
        $dnssecZones = array_filter($this->cachedZoneData, function ($z) {
            if ($z['dnssec'] === false) {
                printf("    UNSIGNED:      %s\n", $z['zone']['origin']);
                return false;
            }
            return true;
        });
        $dnsseclist = fopen("dnsseckeydata.json", "w");
        fwrite($dnsseclist, json_encode(
            array_map(
                function ($z) {
                    return ['DS' => $z['dnssec']['DS'], 'DNSKEY' => $z['dnssec']['KSK']];
                },
                $dnssecZones
            )
        ));
        fclose($dnsseclist);

        CONSOLE::success(sprintf("Export finished. %s DNSSEC Keys exported.", count($dnssecZones)));
        return $this;
    }
}