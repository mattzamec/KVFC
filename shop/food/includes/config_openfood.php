<?php

// Include commonly-used functions
include_once ('general_functions.php');

// Access parameters for the database and OFS configuration table
//$database_config = array (
//  'db_host'         => 'localhost',                          // Enter the db host
//  'db_user'         => 'kvfc_user',                      // Enter the username for db access
//  'db_pass'         => 'kvfc_password',                  // Enter the password for db access
//  'db_name'         => 'kvfc',                     // Enter the database name
//  'db_prefix'       => 'kvfc_',                               // This is probably blank
//  'openfood_config' => 'configuration'                       // Points to configuration table in database
//  );

// Production config
//$database_config = array (
//  'db_host'         => 'kvfc.fatcowmysql.com',                          // Enter the db host
//  'db_user'         => 'rolyrussell',                      // Enter the username for db access
//  'db_pass'         => 'rolyrussell',                  // Enter the password for db access
//  'db_name'         => 'kvfc',                     // Enter the database name
//  'db_prefix'       => 'kvfc_',                               // This is probably blank
//  'openfood_config' => 'configuration'                       // Points to configuration table in database
//  );

// Dev config
$database_config = array (
  'db_host'         => 'localhost',                          // Enter the db host
  'db_user'         => 'kvfc_user',                      // Enter the username for db access
  'db_pass'         => 'kvfc_password',                  // Enter the password for db access
  'db_name'         => 'kvfc',                     // Enter the database name
  'db_prefix'       => 'kvfc_',                               // This is probably blank
  'openfood_config' => 'configuration'                       // Points to configuration table in database
  );

// Include override values, but only if the file exists
#include_once ("config_override.php"); 

// Establish database connection
connect_to_database ($database_config);

// Set all additional configurations from the database
get_configuration ($database_config, isset($override_config) ? $override_config : []);

// Set the time zone
date_default_timezone_set ('America/Los_Angeles');

// Set error reporting level
ini_set('display_errors', DEBUG); 


// Set error reporting level
// Convert the comma-separated ERROR_FLAGS into boolean constants and bitwise-or them together
if(!is_int(ERROR_FLAGS))
{
    $error_flags = array_reduce(array_map('constant', explode(',', ERROR_FLAGS)), function($a, $b) {
        return $a | $b;
    }, 0);
}
error_reporting ($error_flags);

