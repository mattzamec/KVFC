<?php
include_once '../includes/config_openfood.php';
session_start();
valid_auth('producer,producer_admin,orderex,site_admin');

$product_id = $_POST['product_id'];
$action = $_POST['action'];

if ($action == 'list' || $action == 'unlist') {
    $query = '
      UPDATE '.NEW_TABLE_PRODUCTS.'
      SET listing_auth_type = "'.mysql_real_escape_string($action == 'list' ? 'member' : 'unlisted').'"
      WHERE product_id = "'.mysql_real_escape_string($product_id).'"';
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 731034 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
}

// The following is necessary because this is also called when javascript/ajax is turned off and
// we don't want to send extraneous data back to the output page.
if ($non_ajax_query == false)
  {
    echo $result;
  }
?>