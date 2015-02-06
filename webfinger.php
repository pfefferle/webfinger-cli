#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

$app = new \Application\WebFingerApplication("WebFinger");

$app->add(new \Command\WebfingerCommand);

$app->run();
