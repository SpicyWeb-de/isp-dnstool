<?php
/**
 * A collectin of helper functions used across this tool as well as
 * defining autoload for the library functions and adjusting error handling.
 */

/**
 * Autoloader to automatic load files for classes from namespaced folders
 * @param $class_name
 */
function my_autoload($class_name)
{
    $file = 'lib/'.str_replace('\\','/',$class_name).'.php';
    if(file_exists($file)){
        require_once($file);
    }
}
spl_autoload_register('my_autoload');

set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

/**
 * Helper function to print a massive header
 * @param $title Title in header
 */
function printHeader($title){
    $title = sprintf(" %s =====", $title);
    printf("%'=80s\n", $title);
}

/**
 * Helper function to print a less massive subtitle
 * @param $subtitle Subtitle in subheader
 */
function printSubheader($subtitle){
    $subtitle = sprintf(" %s -----", $subtitle);
    printf("%'-80s\n", $subtitle);
}