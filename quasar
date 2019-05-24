#!/usr/bin/env php
<?php

use Workerman\Worker;


//--------------------------------------------------------------------------
// Global Defines
//--------------------------------------------------------------------------

defined('DS') || define('DS', DIRECTORY_SEPARATOR);

define('BASEPATH', realpath(__DIR__) .DS);

define('SERVER_PATH', BASEPATH .'server' .DS);
define('QUASAR_PATH', BASEPATH .'system' .DS);

//--------------------------------------------------------------------------
// Load The Composer Autoloader
//--------------------------------------------------------------------------

require BASEPATH .'vendor' .DS .'autoload.php';


//--------------------------------------------------------------------------
// Boostrap the Push Server
//--------------------------------------------------------------------------

require SERVER_PATH .'Platform' .DS .'Bootstrap.php';


//--------------------------------------------------------------------------
// Run All Workers
//--------------------------------------------------------------------------

Worker::runAll();
