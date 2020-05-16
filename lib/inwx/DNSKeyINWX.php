<?php
namespace inwx;

use isp\DNSKeyISP;

class DNSKeyINWX{
    /**
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

    private $keystatus = DNS_KEY_PUBLISHED;

    /**
     * DNSKeyINWX constructor. Receives Keydata for one single key listed from INWX
     * @param array $keydata = DNSKeyINWX::$keydata
     */
    public function __construct($keydata)
    {
        $this->keydata = $keydata;
    }

    /**
     * Match this key with the Key provided by ISPConfig for this zone and save the comparison status
     * @param DNSKeyISP $ispKey Exported key from ISPConfig for this zone
     * @return $this
     */
    public function match(DNSKeyISP $ispKey){
        if($ispKey->getPublicKey() === $this->keydata['publicKey'])
            // Public key is identical, This key is published AND known by ISP -> no orphan
            $this->keystatus |= DNS_KEY_KNOWN;
        if($ispKey->getStringRepresentation() === $this->getStringRepresentation())
            // All facts of the DNS entries are identical
            $this->keystatus |= DNS_KEY_DATA_OK;
        return $this;
    }

    /**
     * @return int Comparison status of this key
     */
    public function getKeyStatus(){
        return $this->keystatus;
    }

    /**
     * @return string Publication status on INWX servers for this key
     */
    public function getPublishStatus(){
        return $this->keydata['status'];
    }

    /**
     * @return string ID of this key in INWX database
     */
    public function getKeyID(){
        return $this->keydata['id'];
    }

    /**
     * @return string The origin of this key
     */
    public function __toString(){
        return sprintf("%s.", $this->keydata['ownerName']);
    }

    /**
     * @return string Returns the key data in a default format for compairson
     */
    public function getStringRepresentation() {
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
}
