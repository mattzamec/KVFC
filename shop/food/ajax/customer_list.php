<?php

// This originally used to be split up into pretty much identical customer_list.php and customer_basket.php files
include_once (__DIR__.'/../includes/config_openfood.php');
session_start();
valid_auth('member');

include_once (__DIR__.'/../includes/func.open_basket.php');
include_once (__DIR__.'/../includes/func.update_basket_item.php');

// Get values for this operation
// ... from the environment
$member_id = $_POST['member_id'];
$is_bulk = (isset($_POST['is_bulk']) && $_POST['is_bulk'] == 1);
$delivery_id = $_POST['delivery_id'] ? $_POST['delivery_id'] : 
    ($is_bulk ? (new ActiveBulkCycle())->delivery_id() : (new ActiveCycle())->delivery_id());
// ... from add/subtract from basket
$product_id = $_POST['product_id'];
$product_version = $_POST['product_version'];
$action = $_POST['action'];
// ... from update message
$message = $_POST['message'];

// Abort the operation if we do not have important information
if (!$delivery_id ||
    !$member_id ||
    !$action)
{
    die(debug_print ("ERROR: 545721 ", 'Call without necessary information.', basename(__FILE__).' LINE '.__LINE__));
}

// If a basket is not already open, then open one...
$basket_id = $_POST['basket_id'] ? $_POST['basket_id'] : 0;
if (!$basket_id || $basket_id <= 0)
{
    $basket_info = open_basket (array (
      'member_id' => $member_id,
      'delivery_id' => $delivery_id,
    ));
    // If KVFC had multiple sites or delivery types, we may need to deal at this point with the possibility
    // of not having been able to retrieve default or most recent values for this member when opening the basket.
    // However, at the moment this is not a concern since there is a single site (1) and delivery type ('P' for pickup)
    $basket_id = $basket_info['basket_id'];
}

// We need to have the basket ID at this point in order to continue
if (!$basket_id || $basket_id <= 0)
{
    die(debug_print ("ERROR: 545722 ", 'Cannot continue without basket ID.', basename(__FILE__).' LINE '.__LINE__));
}

// If we're checking out the whole basket, do it now and return
$checkout = 0;   // will be set to 1 for item checkout, or 2 for basket checkout
if ($action == 'checkout_basket')
{
    // Check out the whole basket
    $basket_info = update_basket(array(
      'basket_id' => $basket_id,
      'delivery_id' => $delivery_id,
      'member_id' => $member_id,
      'action' => 'checkout'
      ));
    if (!$non_ajax_query)
    {
        echo '0:0:'.($basket_info['checked_out'] != 0 ? '2' : '0');
    }
    return;
}

// At this point we need to make sure we have product information (if we're doing anything other than checking out a basket)
if (!$product_id || !$product_version)
{
    die(debug_print ("ERROR: 545725 ", 'Call without necessary information.', basename(__FILE__).' LINE '.__LINE__));
}

// Make sure the quantity we think is in the basket is the quantity that really is in the basket
$basket_quantity = 0;
$inventory_id = 0;
$inventory_quantity = 0;
$inventory_pull = 1;

$query = '
  SELECT
    (
      SELECT CONCAT(bpid,":",quantity)
      FROM '.NEW_TABLE_BASKET_ITEMS.'
      WHERE basket_id = "'.mysql_real_escape_string ($basket_id).'"
      AND product_id = "'.mysql_real_escape_string ($product_id).'"
      AND product_version = "'.mysql_real_escape_string ($product_version).'"
    ) AS bpid_quantity,
    IFNULL('.NEW_TABLE_PRODUCTS.'.inventory_id, 0) AS `inventory_id`,
    CASE WHEN IFNULL('.NEW_TABLE_PRODUCTS.'.inventory_id, 0) = 0 THEN 0 ELSE '.NEW_TABLE_PRODUCTS.'.inventory_pull END AS `inventory_pull`,
    IFNULL(FLOOR('.TABLE_INVENTORY.'.quantity / '.NEW_TABLE_PRODUCTS.'.inventory_pull), 0) AS `inventory_quantity`
  FROM '.NEW_TABLE_PRODUCTS.'
  LEFT JOIN '.TABLE_INVENTORY.' ON '.TABLE_INVENTORY.'.inventory_id = '.NEW_TABLE_PRODUCTS.'.inventory_id
  WHERE '.NEW_TABLE_PRODUCTS.'.product_id = "'.mysql_real_escape_string ($product_id).'"
  AND '.NEW_TABLE_PRODUCTS.'.product_version = "'.mysql_real_escape_string ($product_version).'"';

$result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 738102 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
if ($row = mysql_fetch_object($result))
{
    list ($bpid,$basket_quantity) = explode(':', $row->bpid_quantity);
    $inventory_quantity = $row->inventory_quantity;
    $inventory_id = $row->inventory_id;
    $inventory_pull = $row->inventory_pull;
}

// These booleans will determine the action to be taken (INSERT/UPDATE/DELETE)
$add_basket_item = false;
$update_basket_item = false;
$remove_basket_item = false;

// If we're adding an item to a basket, and the product is either not inventory tracked or has inventory ...
if ($action == "add" && (!$inventory_id || $inventory_quantity))
{
    $add_basket_item = ($basket_quantity == 0); // Adding a new basket item
    $update_basket_item = !$add_basket_item;    // Updating existing basket item

    $basket_quantity += 1;
    $inventory_quantity -= 1;
}
elseif ($action == "sub")
{
    $remove_basket_item = ($basket_quantity <= 1); // If there is 1 or no basket items, we'll delete the record
    $update_basket_item = !$remove_basket_item;    // If there are multiple basket items, we'll update 

    $basket_quantity -= 1;
    $inventory_quantity += 1;
}

// Take the appropriate INSERT/UPDATE/DELETE action
if ($add_basket_item)
{
    $query = '
      INSERT INTO '.NEW_TABLE_BASKET_ITEMS.' (
        basket_id,
        product_id,
        product_version,
        quantity,
        product_fee_percent,
        subcategory_fee_percent,
        producer_fee_percent,
        out_of_stock,
        date_added )
      SELECT
        "'.mysql_real_escape_string($basket_id).'",
        '.NEW_TABLE_PRODUCTS.'.product_id,
        '.NEW_TABLE_PRODUCTS.'.product_version,
        "1",
        '.NEW_TABLE_PRODUCTS.'.product_fee_percent,
        '.TABLE_SUBCATEGORY.'.subcategory_fee_percent,
        '.TABLE_PRODUCER.'.producer_fee_percent,
        "0",
        "'.date('Y-m-d H:i:s',time()).'"
      FROM '.NEW_TABLE_PRODUCTS.'
      LEFT JOIN '.TABLE_SUBCATEGORY.' USING(subcategory_id)
      LEFT JOIN '.TABLE_PRODUCER.' USING(producer_id)
      WHERE product_id = "'.mysql_real_escape_string ($product_id).'"
      AND product_version = "'.mysql_real_escape_string ($product_version).'"';
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 155816 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    $bpid= mysql_insert_id();
}
else if ($update_basket_item)
{
    $query = '
      UPDATE '.NEW_TABLE_BASKET_ITEMS.'
      SET quantity = "'.mysql_real_escape_string ($basket_quantity).'"
      WHERE basket_id = "'.mysql_real_escape_string ($basket_id).'"
      AND product_id = "'.mysql_real_escape_string ($product_id).'"
      AND product_version = "'.mysql_real_escape_string ($product_version).'"';
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 731034 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
}
else if ($remove_basket_item)
{
    $query = '
      DELETE FROM '.NEW_TABLE_BASKET_ITEMS.'
      WHERE basket_id = "'.mysql_real_escape_string ($basket_id).'"
      AND product_id = "'.mysql_real_escape_string ($product_id).'"
      AND product_version = "'.mysql_real_escape_string ($product_version).'"';
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 267490 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
}

// Adjust the inventory table if applicable
if ($inventory_id && ($action == 'add' || $action == 'sub'))
{
    $query = '
      UPDATE '.TABLE_INVENTORY.'
      SET quantity = quantity '.($action == 'add' ? '- ' : '+ ').mysql_real_escape_string ($inventory_pull).'
      WHERE inventory_id = '.mysql_real_escape_string ($inventory_id);
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 066934 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
}

// Handle messages
// First remove all messages, no matter what. Without this process, additional messages keep getting added.
if (isset ($bpid))
{ // Delete if necessary
    $query = '
      DELETE FROM '.NEW_TABLE_MESSAGES.'
      WHERE referenced_key1 = "'.mysql_real_escape_string($bpid).'"
      AND message_type_id =
          (
            SELECT message_type_id
            FROM '.NEW_TABLE_MESSAGE_TYPES.'
            WHERE description = "customer notes to producer"
          )';
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 285097 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    // Now post the message back if needed
    if ($message != '' && !$remove_basket_item)
    { // Update message
        $query = '
          INSERT INTO '.NEW_TABLE_MESSAGES.'
          SET
            message = "'.mysql_real_escape_string($message).'",
            message_type_id = 
              (
                SELECT message_type_id
                FROM '.NEW_TABLE_MESSAGE_TYPES.'
                WHERE description = "customer notes to producer"
              ),
            referenced_key1 = "'.mysql_real_escape_string($bpid).'"';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 925223 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    }
}

if ($action == 'checkout')
{
    // Make sure there is a good basket for this order
    $basket_item_info = update_basket_item(array (
      'action' => 'checkout',
      'delivery_id' => $delivery_id,
      'member_id' => $member_id,
      'product_id' => $product_id,
      'product_version' => $product_version,
      'messages' => $message
    ));
    if ($basket_item_info['checked_out'] != 0) 
    {
        $checkout = 1;
    }
}

// The following is necessary because this is also called when javascript/ajax is turned off and
// we don't want to send extraneous data back to the output page.
if (!$non_ajax_query)
{
    echo number_format($basket_quantity, 0).':'.number_format($inventory_quantity, 0).':'.$checkout.':'.$alert;
}
