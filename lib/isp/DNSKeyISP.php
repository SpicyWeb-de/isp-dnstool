<?php
/**
 * Home of the DNSKeyISP class
 */
namespace isp;

/**
 * Represents the key information exported from ISPConfig for matching with remote keys
 * @package isp
 */
class DNSKeyISP{
    /**
     * @var array = [
     *      'record'    => 'domain.tld. IN DS 42 7 2 E78C1A...',        // The full DS DNS Record
     *      'origin'    => 'domain.tld.',                               // Origin domain
     *      'id'        => '42',                                        // ID (key tag) of this entry
     *      'cipher'    => '7',                                         // Cipher used for corresponding DNSKEY
     *      'hashtype'  => '2',                                         // Hash algorithm used for digest hash
     *      'hash'      => 'E78C1A...',                                 // The digest hash
     * ]
     */
    private $ds;
    /**
     * @var array = [
     *      'record'    => 'domain.tld. IN DNSKEY 257 3 7 Awc4XtZ8...', // The full DNSKEY DNS Record
     *      'origin'    => 'domain.tld.',                               // Origin domain
     *      'type'      => '257',                                       // Type of published Key (257: Key signing key for DNSSEC)
     *      'protocol'  => '3',                                         // Protocol this key is used for (3: DNSSEC)
     *      'cipher'    => '7',                                         //
     *      'key'       => 'Awc4XtZ8...',                               // The public key to publish as DNSKEY for DNSSEC
     * ]
     */
    private $dnskey;

    /**
     * DNSKeyISP constructor. Receives exported keydata from ISPConfig for this zone
     * @param array $keys = [
     *     'DNSKEY' => DNSKeyISP::$dnskey,
     *     'DS' => DNSKeyISP::$ds
     * ]
     */
    public function __construct($keys)
    {
        $this->dnskey = $keys['DNSKEY'];
        $this->ds = $keys['DS'];
    }

    /**
     * Get a simple string representation of this key for printing in lists
     * @return string The origin of this key
     */
    public function __toString(){
        return sprintf("%s", $this->dnskey['origin']);
    }

    /**
     * Generate a string representation containing all relevant key information for key compairson and detail printout
     * @return string Returns the key data in a default format for compairson
     */
    public function getStringRepresentation(){
        return sprintf(DNS_KEY_COMPARE_FORMAT,
            $this->dnskey['origin'],
            $this->dnskey['type'],
            $this->dnskey['protocol'],
            $this->dnskey['cipher'],
            $this->dnskey['key'],
            $this->ds['id'],
            $this->ds['cipher'],
            $this->ds['hashtype'],
            $this->ds['hash']
        );
    }

    /**
     * Get the DNSSec public key exported from ISPConfig
     * @return string The cryptographic public key
     */
    public function getPublicKey(){
        return $this->dnskey['key'];
    }

    /**
     * Get the full qualified domain name of the corresponding ISPConfig DNS zone
     * @return string The FQDN of the signed zone (without trailing .)
     */
    public function getFqdn(){
        return substr($this->dnskey['origin'], 0, -1);
    }

    /**
     * Generate the DNSKEY Record string for this key
     * @return string DNSKEY record in standardized DNS Record Format
     */
    public function getDNSKEYRecord(){
        return sprintf("%s IN DNSKEY %s %s %s %s",
            $this->dnskey['origin'],
            $this->dnskey['type'],
            $this->dnskey['protocol'],
            $this->dnskey['cipher'],
            $this->dnskey['key']
        );
    }

    /**
     * Generate the DS Record string for this key
     * @return string DS record in standardized DNS Record Format
     */
    public function getDSRecord(){
        return sprintf("%s IN DS %s %s %s %s",
            $this->ds['origin'],
            $this->ds['id'],
            $this->ds['cipher'],
            $this->ds['hashtype'],
            $this->ds['hash']
        );
    }
}