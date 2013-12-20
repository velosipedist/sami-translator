<?php
$autoloadPath = __DIR__ . '/../../../../vendor/autoload.php';
$loader = require_once $autoloadPath;
$loader->add('tests', __DIR__);