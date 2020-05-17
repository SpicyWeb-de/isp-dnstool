<?php
/**
 * DNSKeyINWX class
 */
namespace inwx;

use core\DnssecRecordRemote;

/**
 * Class DNSKeyINWX
 * Representation of a single key entry as reported from INWX remote api
 * @package inwx
 */
class DnssecRecordInwx extends DnssecRecordRemote {
    /**
     * Data of this key as reported from INWX
     * @var array = [
     *      'ownerName'    => 'mydomain.tld',           // Origin name of signed dns zone
     *      'id'           => '8837',                   // ID of this key in INWX database
     *      'domainId'     => '9932',                   // ID of corresponding domain in INWX database
     *      'keyTag'       => '7873',                   // Identical to id field of DNSSEC Key from ISPConfig, used in DNS Record
     *      'flagId'       => ['256', '257'],           // Type of key. 256 for zone signing and 257 for key signing -> registrar needs 257
     *      'algorithmId'  => ['1', '2', '3', '4', '5', '6', '7', '8', '10', '12', '13', '14'],   // Crypo Cipher of the key
     *      'publicKey'    => 'AkLudlSDKJsd...',        // The public key to publish as DNSKEY
     *      'digestTypeId' => ['1', '2', '3', '4'],     // Digest Hash Type
     *      'digest'       => 'E883B2...',              // Digest Hash
     *      'created'      => '2020-05-14 16:19:17',   // Creation Timestamp in ISO 8601 Format
     *      'status'       => ['CREATE', 'DELAYED', 'OK', 'DELETE'],    // Key status on INWX key servers
     *      'active'       => '1',
     * ]
     */
    private $keydata;

    /**
     * DNSKeyINWX constructor. Receives Keydata for one single key listed from INWX
     * @param array $keydata = DNSKeyINWX::$keydata
     */
    public function __construct($keydata)
    {
        $this->keydata = $keydata;
    }

    /**
     * Get the publication status of this key from INWX
     * @return string Publication status on INWX servers for this key
     */
    public function getPublishStatus(): string{
        return $this->keydata['status'];
    }

    /**
     * Get the system ID of this key on INWX servers
     * @return string ID of this key in INWX database
     */
    public function getKeyID(): string{
        return $this->keydata['id'];
    }

    /**
     * Get the origin name of the corresponding DNS Zone
     * @return string The origin of this key
     */
    public function __toString(): string{
        return sprintf("%s.", $this->keydata['ownerName']);
    }

    /**
     * Generate a string representation containing all relevant key information for key compairson and detail printout
     * @return string Returns the key data in a default format for compairson
     */
    public function getStringRepresentation(): string {
        return sprintf(DNS_KEY_COMPARE_FORMAT,
            $this->keydata['ownerName'].'.',
            $this->keydata['flagId'],
            '3',
            $this->keydata['algorithmId'],
            $this->keydata['publicKey'],
            $this->keydata['keyTag'],
            $this->keydata['algorithmId'],
            $this->keydata['digestTypeId'],
            $this->keydata['digest']
        );
    }

    /**
     * Get the full qualified domain name of the corresponding ISPConfig DNS zone
     * @return string The FQDN of the signed zone (without trailing .)
     */
    public function getFqdn(): string{
        return $this->keydata['ownerName'];
    }

    /**
     * Get the DNSSec public key exported from ISPConfig
     * @return string The cryptographic public key
     */
    public function getPublicKey(): string{
        return $this->keydata['publicKey'];
    }
}
