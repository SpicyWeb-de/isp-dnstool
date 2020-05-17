<?php
/**
 * Abstract class DnssecRecordRemote
 */

namespace core;
use isp\DnssecRecordISP;

/**
 * Class DnssecRecordRemote that must be implemented by all classes for representation of
 * remote Dnssec Records received from registry API
 * @package core
 */
abstract class DnssecRecordRemote implements DnssecRecord
{
    /**
     * Status of this key in compairson to ISPConfig information on the corresponding DNS Zone
     * See defines.php for used status flags
     * @var int
     */
    protected $keystatus = DNS_KEY_PUBLISHED;

    /**
     * Constructor receiving the key data from the remote api and storing them in a member attribute
     * @param $keydata mixed
     */
    public abstract function __construct($keydata);

    /**
     * Match this key with the Key provided by ISPConfig for this zone and save the comparison status
     * @param DnssecRecordISP $ispKey Exported key from ISPConfig for this zone
     * @return $this
     */
    public function match(DnssecRecordISP $ispKey): self {
        if($ispKey->getPublicKey() === $this->getPublicKey())
            // Public key is identical, This key is published AND known by ISP -> no orphan
            $this->keystatus |= DNS_KEY_KNOWN;
        if($ispKey->getStringRepresentation() === $this->getStringRepresentation())
            // All facts of the DNS entries are identical
            $this->keystatus |= DNS_KEY_DATA_OK;
        return $this;
    }

    /**
     * Get the status from this key compared it to ISPConfig key data
     * @return int Comparison status of this key
     */
    public function getKeyStatus(): int {
        return $this->keystatus;
    }

    /**
     * Get the publication status of this key on the remote system for reports.
     * e.g. Created, OK, Live, Pending, ...
     * @return string
     */
    public abstract function getPublishStatus(): string;

    /**
     * Get an identifier value for this key to use in API requests for manipulating this key
     * @return mixed
     */
    public abstract function getKeyID();
}