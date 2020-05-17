<?php
/**
 * Interface DnssecRecord
 */

namespace core;

/**
 * Interface DnssecRecord, that must be implemented by all Dnssec Record classes for ISPConfig and Remote Records
 * @package core
 */
interface DnssecRecord
{
    /**
     * A custom tostring-implementation returning the zone name of the corresponding DNS zone
     * @return string
     */
    public function __toString(): string;

    /**
     * Building a string based on the format defined in DNS_KEY_COMPARE_FORMAT,
     * that contains all information of this key in a unified form
     * @return string Unified key string
     */
    public function getStringRepresentation(): string;

    /**
     * Get the public key for this DNSSec Record
     * @return string Public Key
     */
    public function getPublicKey(): string;

    /**
     * Get the full qualified domain name of the corresponding ISPConfig DNS zone
     * @return string The FQDN of the signed zone (without trailing .)
     */
    public function getFqdn(): string;
}