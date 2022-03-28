<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use App\Command\GenerateCommand;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$application = new Application();

$application->add(new GenerateCommand);

$application->run();
