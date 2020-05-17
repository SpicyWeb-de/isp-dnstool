<?php
/**
 * ISPClient Api Class
 */

namespace isp;

/**
 * Auto-Connects to ISPConfig Remote API and provides required operations with the client api lib
 * @package isp
 */
class ISPClientApi extends ISPConnector
{
    /**
     * Instance of this class for singleton
     * @var ISPClientApi Instance of class for singleton
     */
    private static $_instance;

    /**
     * Generate and return the singleton instance of this class
     * @return ISPClientApi Instance of ISPClientApi
     */
    public static function instance()
    {
        if (!self::$_instance)
            self::$_instance = new self();
        return self::$_instance;
    }

    /**
     * Get all clients from ISPConfig
     * @return array = [[
     *     'client_id' => '42',
     *     'sys_userid' => '42',
     *     'sys_groupid' => '42',
     *     'sys_perm_user' => 'riud',
     *     'sys_perm_group' => 'riud',
     *     'sys_perm_other' => '',
     *     'company_name' => 'ACME Inc.',
     *     'company_id' => '',
     *     'gender' => ['m', 'f'],
     *     'contact_firstname' => 'Donald',
     *     'contact_name' => 'Duck',
     *     'customer_no' => 'C42',
     *     'vat_id' => '',
     *     'street' => '',
     *     'zip' => '',
     *     'city' => '',
     *     'state' => '',
     *     'country' => '',
     *     'telephone' => '',
     *     'mobile' => '',
     *     'fax' => '',
     *     'email' => '',
     *     'internet' => '',
     *     'icq' => '',
     *     'notes' => '',
     *     'bank_account_iban' => '',
     *     'bank_account_swift' => '',
     *     'paypal_email' => ''
     * ]] All Clients
     */
    public function getAll()
    {
        return self::$isp->getAllClients();
    }
}