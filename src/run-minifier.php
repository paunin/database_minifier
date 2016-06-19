<?php
require_once "../vendor/autoload.php";

use Paunin\DatabaseMinifier\DatabaseMinifier;
use Paunin\DatabaseMinifier\Exception\DatabaseMinifierException;

//Read config
$configFile = array_key_exists(1, $argv) ? $argv[1] : realpath(__DIR__.'/minifier.json');
$config     = json_decode(file_get_contents($configFile), true);

// Small validation
if (!array_key_exists('connections', $config) || !is_array($config['connections'])) {
    throw new DatabaseMinifierException('You have no valid connections in your config file');
}
$connections = $config['connections'];

$relations = [];

if (array_key_exists('relations', $config) && is_array($config['relations'])) {
    $relations = $config['relations'];
}

if (!array_key_exists('directives', $config)) {
    throw new DatabaseMinifierException('You have no directives in your config file');
}

// Run
$minifier = new DatabaseMinifier(
    $config['connections'],
    $relations
);

$directiveCounter = 0;
foreach ($config['directives'] as $directive) {
    $params = array_key_exists('arguments', $directive) ? $directive['arguments'] : [];
    if (!array_key_exists('method', $directive)) {
        throw new DatabaseMinifierException('You have no method for directive #'.$directiveCounter);
    }
    call_user_func_array([$minifier, $directive['method']], $params);
    $directiveCounter++;
}