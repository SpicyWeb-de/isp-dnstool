# dnsapi

A helper tool to sync DNSSEC information from ISPConfig to INWX domain registry

## Disclaimer

I built this tool for my own needs. Currently it includes only sync with INWX and assumes that all ISPConfig domains are hosted by this provider.

I don't say that there wont be future options to sync DNSSec keys with multiple hosters nor there wont be more sync jobs for e.g. domain contacts or to register a domain via CLI.
At the moment I just don't need it and have too less spare time to build such features.
Furthermore I currently don't have accounts with other providers that offer DNSSec AND provide an API to work with it.  

If you find any bugs, please report them as issues.  
Same for features you would like to see in future development of this tool.  
As I said before, I don't have much spare time to develop new features. So please be patient.
This is an free to use open source tool I develop in my spare time.

__Call to Action__  
If you are a coder yourself, want to implement a feature or close a bug and maintain the possibility to update the tool in future,
feel free to place a Pull Request for your changes. Just make sure to keep the code maintainable and methods of your classes as well as attributs and parameters well documented.  
Also if you want to introduce another DNS provider to this tool, feel free to implement it. But please be aware, that to keep it going, I need to rely on you as maintainer for the API functions towards that provider. I cannot test and develop for an API that I don't have credentials for ;)

## What this ist

dnsapi is a CLI tool I wrote to manage the exchange of DNSSec keys between ISPConfig and my Domain Provider INWX.

This consists of two steps of work:

* Export Key Data from ISPConfig (saved to a local JSON file)
* Perform several operations on data from INWX API that use the previously exported data

These operations contain publishing of key data to INWX servers as well as cleaning up corrupt (incomplete) or orphaned (not known by ISPConfig) key entries at INWX.
Further the tool provides several reportings like quantitative summary about your zones, list of signed domains in ISPConfig and INWX and a detailled report table with status information for every signed domain.

## What this isn't

* A full service api client for transferring all possible data between ISPConfig and INWX (yet, maybe in the far future, who knows)
* A api client to work with other registrars than INWX or even multiple ones at the same time (if you want to get involved, see [Disclaimer](#disclaimer))

## Using dnsapi

### Requirements

* __Linux.__  
  I didn't test it on Windows. It might work if you run the main script with the php interpreter from the console.  
  But maybe it will not.
* __PHP 7.2__  
  May run with older versions, though. Simply not tested. 
* __Composer__  
  Get from https://getcomposer.org/
* __ISPConfig Remote User__  
  Required Permissions:
    - Server functions
    - Client functions
    - DNS zone functions
* __INWX User Credentials__  
  Plus __TOTP secret token__ if you enabled Mobile TAN  
  If your Authenticator App is able to show the TOTP Data (like Bitwarden), it should look like that:  
  otpauth://totp/INWX?secret=THISISYOURSECRETDONTTELLANYONE&issuer=authenticator&digits=6&period=30

### Installation

* Clone this repository  
  `git clone https://github.com/SpicyWeb-de/isp-dnstool.git && cd isp-dnstool`
* Install dependencies
  `composer install --no-dev`
* Create environment file for your settings and edit it with your favorite editor
  `cp .env.dist .env`  
  `vi .env` | `nano .env` | `subl .env` | ...
* Adjust Envfile permissions for security and script permissions for convenience  
  `chmod 600 .env && chmod 700 dnsapi.php`
* Ready to go

### Usage

Every action offered for the remote registry API (INWX) requires a cached list of DNSSEC keys from ISPConfig.
This list is generated with a distinct CLI command and is NOT performed automatically to not float your master server with requests.
So make sure to use this command in the beginning and from time to time again to crate and update that list.

Run the script: `./dnsapi.php` without options and commands will print this usage help:

```text
USAGE:
   dnsapi.php <OPTIONS> <COMMAND> ...                                                                                                                                                                                                                                                                                                                                                                                      

OPTIONS:
   -v, --version                                                         print version                                                                                                                                                       

   -h, --help                                                            Display this help screen and exit immediately.                                                                                                                      

   --no-colors                                                           Do not use any colors in output. Useful when piping output to other tools or files.                                                                                 

   --loglevel <level>                                                    Minimum level of messages to display. Default is info. Valid levels are: debug, info, notice, success, warning, error, critical, alert, emergency.                  


COMMANDS:
   This tool accepts a command as first parameter as outlined below:                                                                                                                                                                         


   isp <OPTIONS>

     Load DNSSEC keys from ISPConfig API                                                                                                                                                                                                     
                                                                                                                                                                                                                                             

     -e, --export                                                          Export DNSSEC keys from ISPConfig DNS Zones                                                                                                                       


   inwx <OPTIONS> [<origin>]

     Perform Requests on INWX API. Specify at least one option.                                                                                                                                                                              
                                                                                                                                                                                                                                             

     -s, --summary                                                         Print summary of published keys                                                                                                                                   

     -r, --report                                                          Print detailed report of published keys                                                                                                                           

     -l, --list                                                            Print list of domains known in ISPConfig and INWX                                                                                                                 

     -k <origin>, --keylist <origin>                                       Print key data of a specific origin domain                                                                                                                        

     -p, --publish                                                         Push all keys from ISPConfig that are not yet published to INWX                                                                                                   

     -c <origin>, --clean <origin>                                         Clean corrupted and orphaned keys for a specific domain                                                                                                           

     --cleanorphans                                                        Delete INWX keys that are not known in ISPConfig                                                                                                                  

     --cleancorrupt                                                        Delete INWX keys that are known in ISPConfig but differ in record details                                                                                         


     <origin>                                                              Origin Domain of DNS Zone (e.g. domain.tld.)
```

## Getting started with development

* Fork this repository and clone your fork
* Install dependencies with `composer install`
* Develop your changes
* Document added or changed code (see below)
* Create a pull request and describe your changes, so I can comprehend your implementation

For documentation based type hint of array strucutres in attributes, parametes or return values I use PHPStorm with the plugin [deep-assoc-completion](https://plugins.jetbrains.com/plugin/9927-deep-assoc-completion).
Please use the modules suggested PHPDoc notation for deep array structures to document collections wherever used.

You may have noticed, that there already is a certain level of abstraction into classes each with a (mostly) distinct job.
In some places this is better implemented than in others. 
For all future changes I have the Requirement to myself and every contributor to move the level of maintainable abstraction forward in a direction that might enable development of further sync jobs (domain contacts, ...) or working with other providers.

~~In the current codebase I don't see that options.  
My excues for that is, that this code was never meant to be a CLI tool. It started as a simple top-down sync script without any options nor configuration.~~  
Since my initial commit to GitHub, I worked on furher abstraction of the provider-related code to the core CLI app. Using the remaining INWX related code as starting point it should be quite possible now to develop functions for furhter providers.

### Docs

To generate the class documentation, install PHPDocumentor on your machine (I simply download phpDocumentor.phar from the project page) and run it in the project root.

You will also need to install php-xml and graphviz for successful doc generation.