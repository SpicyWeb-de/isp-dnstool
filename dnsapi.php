#!/usr/bin/php
<?php
if (php_sapi_name() !== 'cli') {
    exit;
}
require 'vendor/autoload.php';
require 'lib/functions.php';
require 'lib/defines.php';
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;
use core\CONSOLE;
use isp\ISPDnssec;
use \inwx\INWXDNSSecApi;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

class DNSAPI extends CLI
{
    // register options and arguments
    protected function setup(Options $options)
    {
        $options->setHelp('A helper tool to sync DNSSEC information from ISPConfig to INWX domain registry');
        $options->registerOption('version', 'print version', 'v');

        $options->registerCommand('isp', 'Load DNSSEC keys from ISPConfig API');
        $options->registerOption('export', 'Export DNSSEC keys from ISPConfig DNS Zones', 'e', false, 'isp');

        $options->registerCommand('inwx', 'Perform Requests on INWX API. Specify at least one option.');
        $options->registerOption('summary', 'Print summary of published keys', 's', false, 'inwx');
        $options->registerOption('report', 'Print detailed report of published keys', 'r', false, 'inwx');
        $options->registerOption('list', 'Print list of domains known in ISPConfig and INWX', 'l', false, 'inwx');
        $options->registerOption('keylist', 'Print key data of a specific origin domain', 'k', 'origin', 'inwx');
        $options->registerOption('publish', 'Push all keys from ISPConfig that are not yet published to INWX', 'p', false, 'inwx');
        $options->registerOption('clean', 'Clean corrupted and orphaned keys for a specific domain', 'c', 'origin', 'inwx');
        $options->registerOption('cleanorphans', 'Delete INWX keys that are not known in ISPConfig', false, false, 'inwx');
        $options->registerOption('cleancorrupt', 'Delete INWX keys that are known in ISPConfig but differ in record details', false, false, 'inwx');

        $options->registerArgument('origin', 'Origin Domain of DNS Zone (e.g. domain.tld.)', false, 'inwx');
    }

    // implement your code
    protected function main(Options $options)
    {

        if($options->getCmd() == 'isp') {
            ISPDnssec::instance()->loadKeys();
            if($options->getOpt('export')){
                ISPDnssec::instance()->exportKeys();
            }
        } elseif($options->getCmd() == 'inwx') {
            if(count($options->getOpt()) === 0){
                CONSOLE::warning("Please pass options to perform INWX actions");
                echo $options->help();
                return;
            }
            // Prepare. Load ISP Export file and published keys from INWX
            INWXDNSSecApi::instance()
                ->loadISPKeys()
                ->loadPublishedKeys()
            // Match the keys from ISPConfig and INWX
                ->matchListedDomains();
            // loop all options and perform API actions in requested order
            foreach($options->getOpt() as $opt => $optval){
                switch($opt){
                    case 'summary':
                        INWXDNSSecApi::instance()->printSummary();
                        break;
                    case 'report':
                        INWXDNSSecApi::instance()->printReport();
                        break;
                    case 'list':
                        INWXDNSSecApi::instance()->printDomainList();
                        break;
                    case 'publish':
                        INWXDNSSecApi::instance()->publishAllUnpublishedKeys();
                        break;
                    case 'clean':
                        INWXDNSSecApi::instance()->cleanCorruptedKeys($optval);
                        INWXDNSSecApi::instance()->cleanOrphanedKeys($optval);
                        break;
                    case 'cleanorphans':
                        INWXDNSSecApi::instance()->cleanOrphanedKeys();
                        break;
                    case 'cleancorrupt':
                        INWXDNSSecApi::instance()->cleanCorruptedKeys();
                        break;
                    case 'keylist':
                        INWXDNSSecApi::instance()->printZoneKeys($optval);
                        break;
                }
            }
        }
        elseif ($options->getOpt('version')) {
            $this->info('1.0.0');
        } else {
            echo $options->help();
        }
    }
}
// execute it
$cli = new DNSAPI();
CONSOLE::registerCli($cli);
$cli->run();