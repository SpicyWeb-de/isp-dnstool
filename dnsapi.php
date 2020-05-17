#!/usr/bin/php
<?php
/**
 * The main entry point for this CLI app.
 * Make executable to run the CLI with your systems default PHP version or run as input file for your favorite PHP binary
 */

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
use \inwx\DnssecApiInwx;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * Class DNSAPI
 *
 * Registers all available options for the CLI application and calls functions of this application dependent on
 * provieded CLI commands and parameters
 */
class DNSAPI extends CLI
{
    /**
     * Register available command- and parameterstructure of the CLI
     *
     * @param Options $options
     */
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

    /**
     * Main function of the CLI application
     *
     * This is where the entered commands, parameters and arguments are evaluated and corresponding library methods are called
     *
     * @param Options $options
     * @throws ErrorException
     */
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
            DnssecApiInwx::instance()
                ->loadISPKeys()
                ->loadRemoteKeys()
            // Match the keys from ISPConfig and INWX
                ->matchListedDomains();
            // loop all options and perform API actions in requested order
            foreach($options->getOpt() as $opt => $optval){
                switch($opt){
                    case 'summary':
                        DnssecApiInwx::instance()->printSummary();
                        break;
                    case 'report':
                        DnssecApiInwx::instance()->printReport();
                        break;
                    case 'list':
                        DnssecApiInwx::instance()->printDomainList();
                        break;
                    case 'publish':
                        DnssecApiInwx::instance()->publishUnpublishedKeys();
                        break;
                    case 'clean':
                        DnssecApiInwx::instance()->cleanCorruptedKeys($optval);
                        DnssecApiInwx::instance()->cleanOrphanedKeys($optval);
                        break;
                    case 'cleanorphans':
                        DnssecApiInwx::instance()->cleanOrphanedKeys();
                        break;
                    case 'cleancorrupt':
                        DnssecApiInwx::instance()->cleanCorruptedKeys();
                        break;
                    case 'keylist':
                        DnssecApiInwx::instance()->printZoneKeys($optval);
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