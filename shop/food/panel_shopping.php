<?php
include_once 'config_openfood.php';
session_start();
valid_auth('member');

include_once ('func.open_update_basket.php');
include_once ('func.get_baskets_list.php');
// include_once ('func.get_delivery_codes_list.php');   // MZ: This creates a pull-down of locations. Since there is only one, we skip this.

$active_cycle = new ActiveCycle();
$active_bulk_cycle = new ActiveBulkCycle();

// If requested to open-basket...
if ($_GET['action'] == 'open_basket' || $_GET['action'] == 'open_bulk_basket')
{
    // Open/update the basket
    $basket_info = open_update_basket(array(
      'member_id' => $_SESSION['member_id'],
      'delivery_id' => $_GET['action'] == 'open_bulk_basket' ? $active_bulk_cycle->delivery_id() : $active_cycle->delivery_id(),
      'site_id' => $_GET['site_id'] ?: 1,
      'delivery_type' => $_GET['delivery_type'] ?: 'P'
    ));
}

// Get basket status information
$query = '
  SELECT
    SUM(quantity) AS basket_quantity,
    '.NEW_TABLE_BASKETS.'.basket_id
  FROM '.NEW_TABLE_BASKETS.'
  LEFT JOIN '.NEW_TABLE_BASKET_ITEMS.' ON '.NEW_TABLE_BASKETS.'.basket_id = '.NEW_TABLE_BASKET_ITEMS.'.basket_id
  WHERE '.NEW_TABLE_BASKETS.'.member_id = "'.mysql_real_escape_string ($_SESSION['member_id']).'"
  AND '.NEW_TABLE_BASKETS.'.delivery_id = '.mysql_real_escape_string ($active_cycle->delivery_id()).'
  GROUP BY '.NEW_TABLE_BASKETS.'.basket_id';
$result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 657922 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
$basket_quantity = 0;
if ($row = mysql_fetch_object($result))
{
    $basket_quantity = $row->basket_quantity;
    $basket_id = $row->basket_id;
}
if ($active_cycle->is_open_for_ordering())
{
    if ($basket_id)
    {
        $basket_status = '<span class="basket_status_open">Ready for shopping<br>'.$basket_quantity.' '.Inflect::pluralize_if($basket_quantity, 'item').' in basket.</span>';
    }
    else
    {
          // MZ: Replaced the below prompt to select a location to just a link, since there is only one location.
          // Also, HARDCODED site_id = 1; if ever multiple sites come around, this will need to be reverted to the original.
//        $basket_status = '<em>Use Select Location (above) to open a shopping basket</em>';
          $basket_status = '<span class="basket_status_open"><a href="'.$_SERVER['SCRIPT_NAME'].'?action=open_basket&site_id=1&delivery_type=P">Click here to open a shopping basket.</a></span>';
    }
}
else
{
    $basket_status = '<span class="basket_status_closed">Ordering is currently closed<br>'.$basket_quantity.' '.Inflect::pluralize_if($basket_quantity, 'item').' in basket.</span>';
}

// Get bulk basket status information
$query = '
  SELECT
    COUNT(product_id) AS basket_quantity,
    '.NEW_TABLE_BASKETS.'.basket_id
  FROM '.NEW_TABLE_BASKETS.'
  LEFT JOIN '.NEW_TABLE_BASKET_ITEMS.' ON '.NEW_TABLE_BASKETS.'.basket_id = '.NEW_TABLE_BASKET_ITEMS.'.basket_id
  WHERE '.NEW_TABLE_BASKETS.'.member_id = "'.mysql_real_escape_string ($_SESSION['member_id']).'"
  AND '.NEW_TABLE_BASKETS.'.delivery_id = '.mysql_real_escape_string ($active_bulk_cycle->delivery_id()).'
  GROUP BY '.NEW_TABLE_BASKETS.'.member_id';
$result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 657922 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
$bulk_basket_quantity = 0;
if ($row = mysql_fetch_object($result))
{
    $bulk_basket_quantity = $row->basket_quantity;
    $bulk_basket_id = $row->basket_id;
}
if ($active_bulk_cycle->is_open_for_ordering())
{
    if ($bulk_basket_id)
    {
        $bulk_basket_status = '<span class="basket_status_open">Ready for shopping<br>'.$bulk_basket_quantity.' '.Inflect::pluralize_if($bulk_basket_quantity, 'item').' in bulk basket.</span>';
    }
    else
    {
          // MZ: Replaced the below prompt to select a location to just a link, since there is only one location.
          // Also, HARDCODED site_id = 1; if ever multiple sites come around, this will need to be reverted to the original.
//        $bulk_basket_status = '<em>Use Select Location (above) to open a shopping basket</em>';
          $bulk_basket_status = '<span class="basket_status_open"><a href="'.$_SERVER['SCRIPT_NAME'].'?action=open_bulk_basket&site_id=1&delivery_type=P">Click here to open a bulk shopping basket.</a></span>';
    }
}
else
{
    $bulk_basket_status = '<span class="basket_status_closed">Bulk ordering is currently closed<br>'.$bulk_basket_quantity.' '.Inflect::pluralize_if($bulk_basket_quantity, 'item').' in basket.</span>';
}

// Set content_top to show basket selector...
// MZ - commenting out since there is only a single site and no need to select it
//$delivery_codes_list .= get_delivery_codes_list (array (
//  'action' => $_GET['action'],
//  'member_id' => $_SESSION['member_id'],
//  'delivery_id' => $active_cycle->delivery_id(),
//  'site_id' => $_GET['site_id'],
//  'delivery_type' => $_GET['delivery_type']
//  ));

$basket_history .= get_baskets_list();

// Generate the display output
$display .= '
<div class="compact">
  <div class="cycle_wrapper '.($active_cycle->is_open_for_ordering() ? 'cycle_open' : 'cycle_closed').'">  <!-- Regular order cycles -->
    <div>
      <div class="basket_status">'.$basket_status.'</div>'.
      ($basket_history ? '
      '.$basket_history : '').'
    </div>
    <div style="clear: left;">
      <img src="'.DIR_GRAPHICS.'product.png" width="32" height="32" align="left" hspace="2" alt="Order Info"><br>
      <b>Order Info</b>
      <ul class="fancyList1">
        <li><a href="product_list.php?type=basket">View items in basket</a></li>
        <li><a href="show_report.php?type=customer_invoice">View invoice</a>&nbsp;&nbsp;<em>(Invoice is blank until after the order closes)</em></li>
        <li class="last_of_group"><a href="past_customer_invoices.php?member_id='.$_SESSION['member_id'].'">Past customer invoices</a></li>
      </ul>
      <img src="'.DIR_GRAPHICS.'invoices.png" width="32" height="32" align="left" hspace="2" alt="Available Products"><br>
      <b>'.($active_cycle->is_open_for_ordering() ? 'Available Products' : 'Products (Shopping is closed)').'</b>
      <ul class="fancyList1">';

$search_display = '
        <form action="product_list.php" method="get">
          <input type="hidden" name="type" value="search">
          <input type="text" name="query" value="'.$_GET['query'].'">
          <input type="submit" name="action" value="Search">
        </form>';

$display .= '
        <li class="last_of_group">'.$search_display.'</li>';

$display .= '
        <li>                        <a href=category_list2.php>                     Browse by category</a></li>
        <li>                        <a href="prdcr_list.php">                       Browse by producer</a></li>
        <li class="last_of_group">  <a href="product_list.php?type=prior_baskets">  Previously ordered products</a></li>
        <li>                        <a href="product_list.php?type=by_id">          All products by number</a></li>
        <li class="last_of_group">  <a href="product_list.php?type=full">           All products by category</a></li>
        <li>                        <a href="product_list.php?type=organic">        Organic products</a></li>
        <li>                        <a href="product_list.php?type=new">            New products</a></li>
        <li>                        <a href="product_list.php?type=changed">        Changed products</a></li>
      </ul>
    </div>
  </div>
  <div class="cycle_wrapper '.($active_bulk_cycle->is_open_for_ordering() ? 'cycle_open' : 'cycle_closed').'">   <!-- Bulk order cycles -->
    <div>
      <div class="basket_status">'.$bulk_basket_status.'</div>'.
      ($bulk_basket_history ? '
      '.$bulk_basket_history : '').'
    </div>
    <div style="clear: left;">
      <img src="'.DIR_GRAPHICS.'product.png" width="32" height="32" align="left" hspace="2" alt="Order Info"><br>
      <b>Order Info</b>
      <ul class="fancyList1">
        <li><a href="product_list.php?type=basket&show_bulk=1">View items in bulk basket</a></li>
        <li><a href="show_report.php?type=customer_invoice">View bulk invoice</a>&nbsp;&nbsp;<em>(Invoice is blank until after the order closes)</em></li>
        <li class="last_of_group"><a href="past_customer_invoices.php?member_id='.$_SESSION['member_id'].'">Past bulk customer invoices</a></li>
      </ul>
      <img src="'.DIR_GRAPHICS.'invoices.png" width="32" height="32" align="left" hspace="2" alt="Available Products"><br>
      <b>'.($active_bulk_cycle->is_open_for_ordering() ? 'Available Products' : 'Products (Shopping is closed)').'</b>
      <ul class="fancyList1">';

$search_display = '
        <form action="product_list.php" method="get">
          <input type="hidden" name="show_bulk" value="1"/>
          <input type="hidden" name="type" value="search"/>
          <input type="text" name="query" value="'.$_GET['query'].'"/>
          <input type="submit" name="action" value="Search"/>
        </form>';

$display .= '
        <li class="last_of_group">'.$search_display.'</li>';

$display .= '
        <li>                        <a href="category_list2.php?show_bulk=1">       Browse by bulk category</a></li>
        <li class="last_of_group">  <a href="product_list.php?type=prior_baskets">  Previously ordered bulk products</a></li>
        <li>                        <a href="product_list.php?type=by_id&show_bulk=1">          All bulk products by number</a></li>
        <li class="last_of_group">  <a href="product_list.php?type=full&show_bulk=1">           All bulk products by category</a></li>
        <li>                        <a href="product_list.php?type=new&show_bulk=1">            New bulk products</a></li>
        <li>                        <a href="product_list.php?type=changed&show_bulk=1">        Changed bulk products</a></li>
      </ul>
    </div>
  </div>
</div>';

$page_specific_javascript .= '';

$page_specific_css .= '
<link rel="stylesheet" type="text/css" href="delivery_dropdown.css">
<link rel="stylesheet" type="text/css" href="basket_dropdown.css">
<style type="text/css">
#basket_dropdown {
    float:right;
}
.cycle_wrapper {
    border: 4px solid green;
    border-radius: 8px;
    margin: 2px;
    float: left;
    width: 48%;
    min-width: 320px;
}
.cycle_open {
    border-color: #758954;
}
.cycle_closed {
    border-color: #CED5CC;
}
.basket_status {
  font-size:130%;
  font-weight: bold;
  font-variant:small-caps;
  float: left;
  margin: 3px;
}
.basket_status_open {
  color:#58673f;
}
.basket_status_closed {
  color:#6B6B6B;
}
</style>';

// Show the delivery-location chooser ONLY...
// MZ: Note that this could only get triggered from function get_delivery_codes_list, which is currently commented out. 
// Leaving code here for now but it is currently unreachable.
//if ($_GET['action'] == 'delivery_list_only' && $delivery_codes_list)
//  {
//    // Clobber the display and only show the delivery location list
//    $display = $delivery_codes_list;
//    // Add styles to override delivery location dropdown
//    $page_specific_css .= '
//      <style type="text/css">
//      /* OVERRIDE THE DROPDOWN CLOSURE FOR MOBILE DEVICES */
//      #delivery_dropdown {
//        position:static;
//        height:auto;
//        width:100%;
//        overflow:hidden;
//        }
//      #delivery_dropdown:hover {
//        width:100%;
//        height:auto;
//        }
//      #delivery_select {
//        width:100%;
//        height:auto;
//        }
//      </style>';
//  }

if ($_GET['action'] == 'basket_list_only' && $basket_history)
  {
    // Clobber the display and only show the delivery location list
    $display = $basket_history;
    // Add styles to override delivery location dropdown
    $page_specific_css .= '
      <style type="text/css">
      /* OVERRIDE THE DROPDOWN CLOSURE FOR MOBILE DEVICES */
      #basket_dropdown {
        position:static;
        height:auto;
        width:100%;
        overflow:hidden;
        }
      #basket_dropdown:hover {
        width:100%;
        }
      #basket_history {
        width:100%;
        height:auto;
        }
      #basket_dropdown:hover {
        height:auto;
        }
      </style>';
  }

$page_title_html = '<span class="title">'.$_SESSION['show_name'].'</span>';
$page_subtitle_html = '<span class="subtitle">Shopping Panel</span>';
$page_title = 'Shopping Panel';
$page_tab = 'shopping_panel';

include("template_header.php");
echo '
  <!-- CONTENT BEGINS HERE -->
  '.$display.'
  <!-- CONTENT ENDS HERE -->';
include("template_footer.php");