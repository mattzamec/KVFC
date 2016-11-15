<?php

// Override local database connection values. Only need to change values
// that are different from the "official" installation
$database_config ['db_host'] = 'kvfc.fatcowmysql.com';                // Test database server
$database_config ['db_name'] = 'kvfc';           // Test database name
$database_config ['db_user'] = 'rolyrussell';            // Test database userss
$database_config ['db_pass'] = 'rolyrussell';        // Test database password

// Set values that should override database values for this server/installation
// This might be used to configure slight differences between a testing server
// and the production server
//
// Additional override keys (on the left) can be found on the configuration page 
// under site admin on the website.
$override_config = array (
  'site_url'              => 'http://kettlevalleyfoodcoop.org/newshoptest/',                      // Testing URL
  'file_path'             => '/home/users/web/b1830/moo.kvfc/newshoptest',                   // Local file path
  'domainname'            => 'kettlevalleyfoodcoop.org',                                 // Domain name
  'invoice_file_path'     => '/home/users/web/b1830/moo.kvfc/newshoptest/food/invoices/', // Local file path
  'email_member_form'     => 'bogus1@openfoodsource.org',                          // Testing e-mail address
  'email_producer_form'   => 'bogus1@openfoodsource.org',                          // Testing e-mail address
  'md5_master_password'   => '   *** ENTER PASSWORD HASH ***  ',                   // Master password can be gotten from MySQL: SELECT MD5("your_master_password")
  'debug'                 => true,                                                 // Debug mode for testing
  'error_flags'           => 'E_ERROR,E_WARNING,E_PARSE',                        // Error codes for testing
  'bogus'                 => ''                                                    // Catch trailing commas when commenting lines
  );

?>