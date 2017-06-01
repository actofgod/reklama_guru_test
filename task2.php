<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new \ReklamaGuru\Task2\EmailDomainCommand());
$application->add(new \ReklamaGuru\Task2\EmailDomainClearCommand());
$application->run();
