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
            $DnssecApi = \isp\ISPDnssec::instance();
            $DnssecApi->loadKeys();
            if($options->getOpt('export')){
                $DnssecApi->exportKeys();
            }
        } elseif($options->getCmd() == 'inwx') {
            $DnssecApi = \inwx\DnssecApiInwx::instance();
            $this->executeProviderAction($options, $DnssecApi);
        }
        elseif ($options->getOpt('version')) {
            $jsonstring = file_get_contents('composer.json');
            $jsondata = json_decode($jsonstring, true);
            $this->info($jsondata['version']);
        } else {
            echo $options->help();
        }
    }

    /**
     * Execute the provider-specific action that was requested from CLI
     * @param Options $options
     * @param \core\DnssecApi $api Instance of a Provider API class extended from DnssecApi
     */
    private function executeProviderAction(Options $options, \core\DnssecApi $api){
        if(count($options->getOpt()) === 0){
            CONSOLE::warning("Please pass options to perform INWX actions");
            echo $options->help();
            return;
        }
        // loop all options and perform API actions in requested order
        foreach($options->getOpt() as $opt => $optval){
            switch($opt){
                case 'summary':
                    $api->printSummary();
                    break;
                case 'report':
                    $api->printReport();
                    break;
                case 'list':
                    $api->printDomainList();
                    break;
                case 'publish':
                    $api->publishUnpublishedKeys();
                    break;
                case 'clean':
                    $api->cleanCorruptedKeys($optval);
                    $api->cleanOrphanedKeys($optval);
                    break;
                case 'cleanorphans':
                    $api->cleanOrphanedKeys();
                    break;
                case 'cleancorrupt':
                    $api->cleanCorruptedKeys();
                    break;
                case 'keylist':
                    $api->printZoneKeys($optval);
                    break;
            }
        }
    }
}
// execute it
$cli = new DNSAPI();
CONSOLE::registerCli($cli);
$cli->run();