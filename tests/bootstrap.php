<?php
//$autoloadPath = __DIR__ . '/../../../../vendor/autoload.php';
//$loader = require_once $autoloadPath;
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$loader = require_once $autoloadPath;
$loader->add('tests', __DIR__);
$loader->add('velosipedist', __DIR__.'/../src/velosipedist');