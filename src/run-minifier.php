<?php
require_once "../vendor/autoload.php";

use Paunin\DatabaseMinifier\DatabaseMinifier;
use Paunin\DatabaseMinifier\Exception\DatabaseMinifierException;

//Read config
$configFile = array_key_exists(1, $argv) ? $argv[1] : realpath(__DIR__ . '/minifier.json');
$config     = json_decode(file_get_contents($configFile), true);

// Small validation
if (!array_key_exists('connections', $config)) {
    throw new DatabaseMinifierException('You have no connections in your config file');
}
if (!array_key_exists('directives', $config)) {
    throw new DatabaseMinifierException('You have no directives in your config file');
}

// Run
$minifier = new DatabaseMinifier(
    $config['connections']['master'],
    array_key_exists('slave', $config['connections']) ? $config['connections']['slave'] : null
);

foreach ($config['directives'] as $directive) {
    $params = array_key_exists('arguments', $directive) ? $directive['arguments'] : [];
    if (!array_key_exists('method', $directive)) {
        throw new DatabaseMinifierException('You have no method for directive #' . $directiveCounter);
    }
    call_user_func_array([$minifier, $directive['method']], $params);
}