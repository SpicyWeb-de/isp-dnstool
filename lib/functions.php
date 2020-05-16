<?php
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

function printHeader($title){
    $title = sprintf(" %s =====", $title);
    printf("%'=80s\n", $title);
}

function printSubheader($subtitle){
    $subtitle = sprintf(" %s -----", $subtitle);
    printf("%'-80s\n", $subtitle);
}