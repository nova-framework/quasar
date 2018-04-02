<?php

//--------------------------------------------------------------------------
// Quasar Global Configuration
//--------------------------------------------------------------------------

/**
 * Define the path to Storage.
 *
 * NOTE: in a multi-tenant design, every application should have its unique Storage.
 */
define('STORAGE_PATH', BASEPATH .'storage' .DS);

/**
 * Define the global prefix.
 *
 * PREFER to be used in Database calls or storing Session data, default is 'quasar_'
 */
define('PREFIX', 'quasar_');

/**
 * Define the SocketIO Server port.
 */
define('SENDER_PORT', 2120);

/**
 * Define the WEB Server host.
 */
define('SERVER_HOST', '0.0.0.0');

/**
 * Define the WEB Server port.
 */
define('SERVER_PORT', 2121);
