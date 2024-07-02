#!/usr/bin/env php
<?php
// Do not show any errors
ini_set('display_errors', 'Off');
ini_set('display_startup_errors', 'Off');
error_reporting(0);

require __DIR__.'/vendor/autoload.php';

$app = new \Application\WebFingerApplication("WebFinger");

$app->add(new \Command\WebFingerCommand);

$app->run();
