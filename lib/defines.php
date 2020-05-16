<?php
// Define zone status

// Status Flags for Zone and Key status verification
/**
 * FLAGS:
 *  --0 -> Unknown in ISP
 *  --1 -> Known export key from ISP
 *  -0- -> No active published key (not deleted) on INWX
 *  -1- -> Published key on INWX
 *  0-- -> Key data on ISP and INWX do not match
 *  1-- -> Key data on ISP and INWX do match
 */
define('DNS_KEY_NOT_CHECKED', bindec('000'));
define('DNS_KEY_KNOWN', bindec('001'));
define('DNS_KEY_PUBLISHED', bindec('010'));
define('DNS_KEY_DATA_OK', bindec('100'));

/**
 *  Usual Combinations for zones and single INWX keys
 *  000 -> DNS_KEY_NOT_CHECKED          -> Not yet checked
 *  001 -> DNS_KEY_UNPUBLISHED          -> Exported from ISP but not published
 *  010 -> DNS_KEY_UNKNOWN              -> Possible orphaned key on INWX
 *  011 -> DNS_KEY_MISMATCH          -> Keys available on both sides but no INWX key matches ISP key (corrupt data)
 *  111 -> DNS_KEY_OK                -> Matching Key in ISP and INWX (there could still be orphaned or corrupt keys)
 */
define('DNS_KEY_KNOWN_AND_PUBLISHED', DNS_KEY_KNOWN | DNS_KEY_PUBLISHED);
define('DNS_KEY_UNPUBLISHED', 1);
define('DNS_KEY_UNKNOWN', 2);
define('DNS_KEY_OK', DNS_KEY_KNOWN | DNS_KEY_PUBLISHED | DNS_KEY_DATA_OK);

/*
 * String representation of a signed zone, used for comparability between ISPConfig and other systems
 */
define('DNS_KEY_COMPARE_FORMAT', "ZONE   %s\nDNSKEY %s %s %s %s\nDS     %s %s %s %s");