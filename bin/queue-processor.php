#!/usr/bin/env php
<?php
namespace MailRu\QueueProcessor\bin;

use MailRu\QueueProcessor\Processor;
use Mougrim\Logger\Logger;
use Mougrim\Pcntl\SignalHandler;

/*
 * @var \Composer\Autoload\ClassLoader $loader
 */

// require composer autoloader
$autoloadPath = dirname(__DIR__).'/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    /* @noinspection PhpIncludeInspection */
    $loader = require_once $autoloadPath;
} else {
    /* @noinspection PhpIncludeInspection */
    $loader = require_once dirname(dirname(dirname(__DIR__))).'/autoload.php';
}

function help($exitCode = 0)
{
    $commandName = $_SERVER['argv'][0];
    echo "Usage: {$commandName} --config=<config>\n";
    echo "\n";
    echo "  --config=<config> Path to QueueProcessor config\n";
    echo "  -h                This help\n";
    echo "\n";
    exit($exitCode);
}

$params = getopt('h', ['config:', 'help']);
if (!$params) {
    help(1);

    return;
}
if (isset($params['h']) || isset($params['help'])) {
    help();

    return;
}
if (!isset($params['config'])) {
    help(1);

    return;
}
if (!is_file($params['config'])) {
    echo "Config path '{$params['config']}' isn't file\n";
    exit(1);
}
if (!is_readable($params['config'])) {
    echo "Config '{$params['config']}' isn't readable\n";
    exit(1);
}
/* @noinspection PhpIncludeInspection */
$config = require $params['config'];
if (!$config) {
    echo "Config should be not empty\n";
    exit(1);
}
if (!is_array($config)) {
    echo "Config should be array\n";
    exit(1);
}
$loggerConfig = [];
if (isset($config['loggerConfig']) && is_array($config['loggerConfig'])) {
    $loggerConfig = $config['loggerConfig'];
}
Logger::configure($loggerConfig);
$processor = new Processor();
$signalHandler = new SignalHandler();
$processor->setSignalHandler($signalHandler);

if (!isset($config['mainConfig'])) {
    echo "You should specify mainConfig key in config. See documentation for more information.\n";
    exit(1);
}
$processor->setMainConfig($config['mainConfig']);

if (!isset($config['processorConfig'])) {
    echo "You should specify processorConfig key in config. See documentation.\n";
    exit(1);
}
if (!isset($config['processorConfig']['configReader'])) {
    echo "You should specify processorConfig.configReader key in config. See documentation.\n";
    exit(1);
}
$processor->setConfigReaderConfig($config['processorConfig']['configReader']);

if (isset($config['processorConfig']['statusFilePath'])) {
    $processor->setStatusFilePath($config['processorConfig']['statusFilePath']);
}

$processor->run();
