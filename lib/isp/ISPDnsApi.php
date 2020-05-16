<?php


namespace isp;

class ISPDnsApi extends ISPConnector
{
    /**
     * @var ISPDnsApi Singleton instance
     */
    private static $_instance;

    /**
     * @return ISPDnsApi Get instance
     */
    public static function instance()
    {
        if (!self::$_instance)
            self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * @return string[] IDs of all DNS Servers registered on Master
     */
    public function getAllServerIds()
    {
        $dnsserverids = [];
        foreach (self::$isp->serverGetAll() as $server) {
            $sid = $server['server_id'];
            $functions = self::$isp->serverGetFunctions($sid);
            if ($functions['dns_server'] === '1')
                array_push($dnsserverids, $sid);
        }
        return $dnsserverids;
    }

    /**
     * Get details of a specific DNS zone from Master
     * @param $id Zone ID
     * @return array = [
     *      'id' => '42',                                               // System ID of this zone
     *      'sys_userid' => '1',                                        // System User ID of zone owner
     *      'sys_groupid' => '1',                                       // System Group ID of owner
     *      'sys_perm_user' => 'riud',                                  // Permissions for Owner
     *      'sys_perm_group' => 'riud',                                 // Permissions for Group
     *      'sys_perm_other' => '',                                     // Permissions for others
     *      'server_id' => '2',                                         // ID of DNS server that serves this zone
     *      'origin' => 'domain.tld.',                                  // Origin name of this zone
     *      'ns' => 'ns1.service.tld.',                                 // FQDN of DNS server that serves this zone
     *      'mbox' => 'admin.domain.tld.',                              // E-Mail or Zone Admin
     *      'serial' => '20200527',                                     // Zone Serial
     *      'refresh' => '7200',                                        // Refresh time for clients
     *      'retry' => '900',                                           // Retry time for clients
     *      'expire' => '604800',                                       // Expire time for this zone
     *      'minimum' => '3600',                                        //
     *      'ttl' => '3600',                                            //
     *      'active' => ['Y', 'N'],                                     // Active status in ISPConfig
     *      'xfer' => '185.181.104.96',                                 // IPs allowed for zone transfer
     *      'also_notify' => '185.181.104.96',                          // IPs to notify in case of changes
     *      'update_acl' => '',                                         //
     *      'dnssec_initialized' => ['Y', 'N'],                         // DNSSec is initialized for this zone
     *      'dnssec_wanted' => ['Y', 'N'],                              // DNSSec is requested for this zone
     *      'dnssec_last_signed' => '1586474961',                       // Time stamp of current DNSSec signature
     *      'dnssec_info' => 'DS-Records: ... DNSKEY-Records: ...',     // DNSSec Key Data for registry
     * ]
     */
    public function getZone($id)
    {
        return self::$isp->dnsZoneGet($id);
    }
}