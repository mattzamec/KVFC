<?php

include_once 'includes/config_openfood.php';
session_start();
// Validations are done in the product_list/* files
// Sanitize variables that are expected to be numeric
if(isset($_GET['producer_id']))
{
    $_GET['producer_id'] = preg_replace('/[^0-9]/', '', $_GET['producer_id']);
}
if(isset($_GET['category_id']))
{
    $_GET['category_id'] = preg_replace('/[^0-9]/', '', $_GET['category_id']);
}
if(isset($_GET['subcat_id']))
{
    $_GET['subcat_id'] = preg_replace('/[^0-9]/', '', $_GET['subcat_id']);
}

// Items dependent upon the location of this header
$unique = array();
$pager = array();

// MZ: It doesn't appear this page is ever POSTed to, so this IF statement will always be false.
if(isset($_POST['action']))
{
    // MZ: It doesn't appear like any of these x/y options are used at all ...
    if(( isset($_POST['basket_x']) && isset($_POST['basket_y'])) ||
            ( isset($_POST['basket_add_x']) && isset($_POST['basket_add_y'])))
    {
        $_POST['action'] = 'add';
    }
    elseif(isset($_POST['basket_sub_x']) && isset($_POST['basket_sub_y']))
    {
        $_POST['action'] = 'sub';
    }
    $process_type = $_POST['process_type'];
    $non_ajax_query = true;
    // Different back-end for customer_list|basket_list|producer_basket
    include(FILE_PATH.PATH.'ajax/'.$process_type.'.php');
}

// Set up some variables that might be needed
if($_SESSION['member_id'])
{
    $member_id = $_SESSION['member_id'];
}
// Allow cashier to override member_id
if($_GET['member_id'] && CurrentMember::auth_type('cashier'))
{
    $member_id = $_GET['member_id'];
}

if($_GET['producer_id'])
{
    $producer_id = $_GET['producer_id'];
}
if($_GET['producer_link'])
{
    $producer_link = $_GET['producer_link'];
}
if($_SESSION['producer_id_you'])
{
    $producer_id_you = $_SESSION['producer_id_you'];
}
// Allow GET to trump SESSION for producer_id -- but only for admin
if(CurrentMember::auth_type('producer_admin') && isset($_GET['producer_id']))
{
    $producer_id_you = $_GET['producer_id'];
}

// Get order cycle information
$show_bulk = isset($_GET['show_bulk']) && $_GET['show_bulk'] == 1;
$active_cycle = $show_bulk ? new ActiveBulkCycle() : new ActiveCycle();
$next_cycle = $show_bulk ? new NextBulkCycle() : new NextCycle();

// Get a delivery_id for pulling current producer "invoices"
$delivery_id = mysql_real_escape_string($_GET['delivery_id'] ? $_GET['delivery_id'] : $active_cycle->delivery_id());

// Determine whether the order is open or not
$order_open = false;
if(($active_cycle->is_open_for_ordering() && $active_cycle->delivery_id() == $delivery_id ) ||
        CurrentMember::auth_type('orderex, bulk_admin'))
{
    $order_open = true;
}

// Initialize display of wholesale and retail to false
$display_wholesale_price = false;
$display_retail_price = false;
$is_wholesale_item = false;

// SET UP QUERY PARAMETERS THAT APPLY TO MOST LISTS
// Only show for listed producers -- not unlisted (1) or suspended (2)
$where_unlisted_producer = '
    AND unlisted_producer = 0';

// Normally, do not show producers that are pending (1)
$where_producer_pending = '
    '.TABLE_PRODUCER.'.pending = 0';

// Set up an exception for hiding zero-inventory products
$where_zero_inventory = '';
if(EXCLUDE_ZERO_INV)
{
    // Can use TABLE_PRODUCT here because this condition is only used on the public product lists
    $where_zero_inventory = '
    AND (
      IF('.NEW_TABLE_PRODUCTS.'.inventory_id > 0, FLOOR('.TABLE_INVENTORY.'.quantity / '.NEW_TABLE_PRODUCTS.'.inventory_pull), 1)
      OR '.NEW_TABLE_BASKET_ITEMS.'.quantity > 0)';
}

// Set the default subquery_confirmed to look only at confirmed products
$where_confirmed .= '
    AND '.NEW_TABLE_PRODUCTS.'.confirmed = 1';

// Set the bulk subquery
$where_bulk = '
    AND '.TABLE_PRODUCER.'.is_bulk '.($show_bulk ? '=' : '!=').' 1';

//////////////////////////////////////////////////////////////////////////////////////
//                                                                                  //
//                         QUERY AND DISPLAY THE DATA                               //
//                                                                                  //
//////////////////////////////////////////////////////////////////////////////////////
// Make sure we're looking for a valid list_type
$list_type = isset($_GET['type']) && file_exists('product_list/'.$_GET['type'].'.php') ? $_GET['type'] : 'by_id';

// Include the appropriate list "module" from the product_list directory.
// These use variables declared earlier in this scope, so the placement of this include is important.
include_once ('product_list/'.$list_type.'.php');
// Now include the template (specified in the include_file) - needs to come after the "module" include above 
// since that one sets $template_type
include_once ('product_list/'.$template_type.'_template.php');

// This setting might be overridden below or in included files
$pager['per_page'] = PER_PAGE;
// Labels do not have pages
if($template_type == 'labels')
{
    $pager['per_page'] = 1000000;
}
// Set up the pager for the output
$list_start = ($_GET['page'] - 1) * $pager['per_page'];
if($list_start < 0)
{
    $list_start = 0;
}
$query_limit = $list_start.', '.$pager['per_page'];

// Add limits to the query
$query .= '
  LIMIT '.$query_limit;

$result = mysql_query($query, $connection) or die(debug_print("ERROR: 785033 ", array($query, mysql_error()), basename(__FILE__).' LINE '.__LINE__));
// Get the total number of rows (for pagination) -- not counting the LIMIT condition
$query_found_rows = '
  SELECT
    FOUND_ROWS() AS found_rows';
$result_found_rows = mysql_query($query_found_rows, $connection) or die(debug_print("ERROR: 860342 ", array($query, mysql_error()), basename(__FILE__).' LINE '.__LINE__));
// Handle pagination for multi-page results
$row_found_rows = mysql_fetch_array($result_found_rows);
$pager['found_rows'] = $row_found_rows['found_rows'];
$pager['this_page'] = $_GET['page'] ? $_GET['page'] : 1;
$pager['last_page'] = ceil(($pager['found_rows'] / $pager['per_page']) - 0.00001);
$pager['page'] = 0;
while(++$pager['page'] <= $pager['last_page'])
{
    $pager['this_page_true'] = ($pager['page'] == $pager['this_page']);
    $pager['display'] .= pager_display_calc($pager);
}
$pager_navigation_display = pager_navigation($pager);
$order_cycle_navigation_display = order_cycle_navigation($pager);

// Iterate through the returned results and display products
while($row = mysql_fetch_array($result))
{
    $unique['product_count'] ++;
    // If this row does not contain any product information, then break out of the "while" loop
    if($row['product_id'] == '')
    {
        $unique['product_count'] = 0;
        break;
    }
    // Add non-database variables to the $row array so they are available in function calls
    if($row_counter++ < 1) // only do this once
    {
        if(in_array('institution', explode(',', $row['auth_type'])))
        {
            $display_wholesale_price = true;
        }
        else
        {
            $display_retail_price = true;
        }
    }
    $row['display_retail_price'] = $display_retail_price;
    $row['display_wholesale_price'] = $display_wholesale_price;
    $row['is_wholesale_item'] = $is_wholesale_item;
    $row['availability_array'] = explode(',', $row['availability_list']);
    // $row['site_id_you'] = CurrentBasket::site_id();
    // $row['site_short_you'] = CurrentBasket::site_short();
    // $row['site_long_you'] = CurrentBasket::site_long();
    $row['order_open'] = $order_open;

    // Open the product list
    if($first_time_through++ == 0)
    {
        $display .= open_list_top($row);
    }

    // Set the various fees:
    $row['customer_customer_adjust_fee'] = 0;
    $row['producer_customer_adjust_fee'] = 0;
    if(PAYS_CUSTOMER_FEE == 'customer')
    {
        $row['customer_customer_adjust_fee'] = $row['customer_fee_percent'] / 100;
    }
    elseif(PAYS_CUSTOMER_FEE == 'producer')
    {
        $row['producer_customer_adjust_fee'] = $row['customer_fee_percent'] / 100;
    }
    $row['customer_product_adjust_fee'] = 0;
    $row['producer_product_adjust_fee'] = 0;
    if(PAYS_PRODUCT_FEE == 'customer')
    {
        $row['customer_product_adjust_fee'] = $row['product_fee_percent'] / 100;
    }
    elseif(PAYS_PRODUCT_FEE == 'producer')
    {
        $row['producer_product_adjust_fee'] = $row['product_fee_percent'] / 100;
    }
    $row['customer_subcat_adjust_fee'] = 0;
    $row['producer_subcat_adjust_fee'] = 0;
    if(PAYS_SUBCATEGORY_FEE == 'customer')
    {
        $row['customer_subcat_adjust_fee'] = $row['subcategory_fee_percent'] / 100;
    }
    elseif(PAYS_SUBCATEGORY_FEE == 'producer')
    {
        $row['producer_subcat_adjust_fee'] = $row['subcategory_fee_percent'] / 100;
    }
    $row['customer_producer_adjust_fee'] = 0;
    $row['producer_producer_adjust_fee'] = 0;
    if(PAYS_PRODUCER_FEE == 'customer')
    {
        $row['customer_producer_adjust_fee'] = $row['producer_fee_percent'] / 100;
    }
    elseif(PAYS_PRODUCER_FEE == 'producer')
    {
        $row['producer_producer_adjust_fee'] = $row['producer_fee_percent'] / 100;
    }

    // All this parsing and rounding is to match the line-item breakout in the ledger to prevent roundoff mismatch
    //$row['cost_multiplier'] = ($row['random_weight'] == 1 ? $row['total_weight'] : ($row['basket_quantity'] - $row['out_of_stock'])) * $row['unit_price'];
    // The line above is replaced by the following cost_multiplier calculations
    if($row['random_weight'] == 1 && // Random weight item
            $row['total_weight'] == 0 && // AND no weight entered
            ($row['basket_quantity'] - $row['out_of_stock']) != 0)  // AND not out of stock
    {
        switch(RANDOM_CALC)
        {
            case 'ZERO':
                $row['cost_multiplier'] = 0;
                $row['weight_needed'] = true;
                break;
            case 'AVG':
                $row['cost_multiplier'] = ($row['minimum_weight'] + $row['maximum_weight']) / 2 * $row['unit_price'] * ($row['basket_quantity'] - $row['out_of_stock']);
                $row['weight_needed'] = true;
                break;
            case 'MIN':
                $row['cost_multiplier'] = $row['minimum_weight'] * $row['unit_price'] * ($row['basket_quantity'] - $row['out_of_stock']);
                break;
            case 'MAX':
                $row['cost_multiplier'] = $row['maximum_weight'] * $row['unit_price'] * ($row['basket_quantity'] - $row['out_of_stock']);
                break;
        }
        $row['weight_needed'] = true;
    }
    elseif($row['random_weight'] == 1) // Random weight item with weight entered (or out of stock)
    {
        $row['cost_multiplier'] = $row['total_weight'] * $row['unit_price'];
    }
    else // Not a random weight item
    {
        $row['cost_multiplier'] = ($row['basket_quantity'] - $row['out_of_stock']) * $row['unit_price'];
    }

    $row['producer_adjusted_cost'] = round($row['cost_multiplier'], 2) - round($row['producer_customer_adjust_fee'] * $row['cost_multiplier'], 2) - round($row['producer_subcat_adjust_fee'] * $row['cost_multiplier'], 2) - round($row['producer_producer_adjust_fee'] * $row['cost_multiplier'], 2);
    $row['customer_adjusted_cost'] = round($row['cost_multiplier'], 2) + round($row['customer_customer_adjust_fee'] * $row['cost_multiplier'], 2) + round($row['customer_product_adjust_fee'] * $row['cost_multiplier'], 2) + round($row['customer_subcat_adjust_fee'] * $row['cost_multiplier'], 2) + round($row['customer_producer_adjust_fee'] * $row['cost_multiplier'], 2);
    // Following values are for generalalized -- not-logged-in calculations
    $row['retail_unit_cost'] = round($row['unit_price'], 2) + (PAYS_CUSTOMER_FEE == 'customer' ? round($next_cycle->retail_markup() * $row['unit_price'], 2) : 0) + round($row['customer_product_adjust_fee'] * $row['unit_price'], 2) + round($row['customer_subcat_adjust_fee'] * $row['unit_price'], 2) + round($row['customer_producer_adjust_fee'] * $row['unit_price'], 2);
    $row['wholesale_unit_cost'] = round($row['unit_price'], 2) + (PAYS_CUSTOMER_FEE == 'customer' ? round($next_cycle->wholesale_markup() * $row['unit_price'], 2) : 0) + round($row['customer_product_adjust_fee'] * $row['unit_price'], 2) + round($row['customer_subcat_adjust_fee'] * $row['unit_price'], 2) + round($row['customer_producer_adjust_fee'] * $row['unit_price'], 2);

    // These are per-item values baseed on the SHOW_ACTUAL_PRICE setting
    $row['display_unit_wholesale_price'] = SHOW_ACTUAL_PRICE ? $row['wholesale_unit_cost'] : $row['unit_price'];
    $row['display_unit_retail_price'] = SHOW_ACTUAL_PRICE ? $row['retail_unit_cost'] : $row['unit_price'];

    // These are line-item totals based on the SHOW_ACTUAL_PRICE setting
    $row['customer_display_cost'] = SHOW_ACTUAL_PRICE ? $row['customer_adjusted_cost'] : $row['cost_multiplier'];
    $row['producer_display_cost'] = SHOW_ACTUAL_PRICE ? $row['producer_adjusted_cost'] : $row['cost_multiplier'];

    // Set up wholesale flag
    $row['is_wholesale_item'] = ($row['listing_auth_type'] == "institution");

    // Get the availability for this product at this member's chosen site_id
    // Two conditions will allow products to be purchased (availability = true):
    //   1. No availibility set for the producer means the product is available everywhere
    //   2. Customer's site is in the set of availabile locations for the producer
    $row['availability'] = ($row['availability_list'] == '' || in_array($row['site_id_you'], $row['availability_array']));

    $row['row_activity_link'] = row_activity_link_calc($row, $pager, $unique);
    $row['listed_status_cell'] = ($list_type == 'producer_list' ? listed_status_cell($row) : '');
    $row['random_weight_display'] = random_weight_display_calc($row);
    $row['business_name_display'] = business_name_display_calc($row);
    $row['pricing_display'] = pricing_display_calc($row);
    $row['total_display'] = total_display_calc($row, $unique);
    $row['ordering_unit_display'] = ordering_unit_display_calc($row);
    $row['image_display'] = image_display_calc($row);
    $row['prodtype_display'] = prodtype_display_calc($row);
    $row['inventory_display'] = inventory_display_calc($row);
    // New major division
    if($row[$major_division] != $$major_division_prior && $show_major_division)
    {
        if($listing_is_open)
        {
            if($show_minor_division)
            {
                $display .= minor_division_close($row, $unique);
            }
            $display .= major_division_close($row);
            $listing_is_open = 0;
        }
        $display .= major_division_open($row, $major_division);
        // New major division will force a new minor division
        $$minor_division_prior = -1;
    }

    // New minor division
    if($row[$minor_division] != $$minor_division_prior && $show_minor_division)
    {
        if($listing_is_open)
        {
            $display .= minor_division_close($row, $unique);
            $listing_is_open = 0;
        }
        $display .= minor_division_open($row, $minor_division);
    }

    $listing_is_open = 1;
    $display .= show_listing_row($row, $row_type);

    // Handle prior values to catch changes
    $$major_division_prior = $row[$major_division];
    $$minor_division_prior = $row[$minor_division];
}
$unique['completed'] = 'true';
// Close minor if there were any products
if($unique['product_count'] > 0 && $show_minor_division)
{
    $display .= minor_division_close($row, $unique);
}
// Close major if there were any products
if($unique['product_count'] > 0 && $show_major_division)
{
    $display .= major_division_close($row);
}
// Close the product list if there were any products listed
if($pager['found_rows'] && $unique['product_count'] > 0)
{
    $display .= close_list_bottom($row, $unique);
}
// Otherwise send the "nothing to show" message
else
{
    $display .= no_product_message();
    $pager['found_rows'] = 0;
}

// Some product_list types need dynamically generated titles and subtitles
$page_title_html = '<span class="title">Products</span>';
$page_tab = 'shopping_panel';
if($_GET['type'] == 'subcategory')
{
    $page_subtitle_html = '<span class="subtitle">'.$subcategory_name.' Subcategory</span>';
    $page_title = 'Products: '.$subcategory_name.' Subcategory';
}
elseif($_GET['producer_id'] || strpos($_SERVER['SCRIPT_NAME'], 'producers'))
{
    $page_subtitle_html = '<span class="subtitle">'.$business_name.'</span>';
    $page_title = 'Products: '.$business_name;
}

$content_list = '
<div id="listing_auth_type">
  <h3>';
foreach(array("retail" => "Listed Retail", "wholesale" => "Listed Wholesale", "unlisted" => "Unlisted", "archived" => "Archived") as $key => $value)
{
    if($_REQUEST['a'] == $key)
    {
        $content_list .= $value.' ';
        $this_edit = $value;
    }
    else
    {
        $content_list .= '[<a href="producer_product_list.php?a='.$key.'">'.$value.'</a>] ';
    }
}
$content_list .= '</h3>';

if($show_search)
    $search_display = '
  <form action="'.$_SERVER['SCRIPT_NAME'].'" method="get">'.
            ($_REQUEST['a'] ? '<input type="hidden" name="a" value="'.$_REQUEST['a'].'"/>' : '').'
    <input type="text" name="query" value="'.$search_query.'"/>
    <input type="hidden" name="show_bulk" value="'.($show_bulk ? '1' : '0').'"/>
    <input type="submit" name="type" value="search"/>
  </form>';

if(isset($pager['found_rows']))
{
    $search_display .= '
      <span class="found_rows">Found '.$pager['found_rows'].' '.Inflect::pluralize_if($pager['found_rows'], 'item').'</span>';
}

$page_specific_css .= '
<link rel="stylesheet" type="text/css" href="'.PATH.'product_list.css">
<link rel="stylesheet" type="text/css" href="basket_dropdown.css">
<style type="text/css">
#basket_dropdown {
  right:3%;
  }
#content_top {
  margin-bottom:25px;
  }

  #simplemodal-data {
    height:100%;
    background-color:#fff;
    }
  #simplemodal-container {
    box-shadow:10px 10px 10px #000;
    }
  #simplemodal-data iframe {
    border:0;
    height:95%;
    width:100%;
    }
  #simplemodal-container a.modalCloseImg {
    background:url('.DIR_GRAPHICS.'/simplemodal_x.png) no-repeat; /* adjust url as required */
    width:25px;
    height:29px;
    display:inline;
    z-index:3200;
    position:absolute;
    top:0px;
    right:0px;
    cursor:pointer;
  }
.pager a {
  width:'.($pager['last_page'] == 0 ? 0 : number_format(72 / $pager['last_page'], 2)).'%;
  }
.estimate {
  color:#a00;
  font-style:italic;
  }
.basket_total {
  text-align:right;
  }
.total_label {
  font-weight:bold;
  }
.total_label_info {
  font-weight:normal;
  font-size:90%;
  }
.total_message {
  display:block;
  font-size:70%;
  }
.estimate_message {
  display:block;
  color:#a00;
  font-size:70%;
  }
.prior_order {
  display:block;
  width:100%;
  margin-top:1em;
  text-align:center;
  font-size:70%;
  color:#000;
  }
.product_list {
  clear:both;
  }
#delivery_id_nav {
  margin: 5px auto 0;
  max-width: 40rem;
  text-align:center;
  }
#delivery_id_nav .delivery_id {
  display:block;
  font-weight:bold;
  background-color:#464;
  color:#fff;
  border-radius:10px;
  }
#delivery_id_nav .prior,
#delivery_id_nav .next,
#delivery_id_nav .delivery_id {
  display: inline-block;
  line-height: 1.5;
  padding: 0 15px;
  }
</style>';

$page_specific_javascript .= '
<script type="text/javascript">
function GetQueryStringValue(parameterName)
{
    var queryArray = window.location.search.substring(1).split(\'&\');
    for (var i = 0; i < queryArray.length; i++) {
        var currentParameter = queryArray[i].split(\'=\');
        if (currentParameter[0] == parameterName) {
            return currentParameter[1];
        }
    }
    return "";
}

function ListProduct(product_id) {
    jQuery.post("'.PATH.'ajax/product_update_listed_status.php", 
    {
        product_id:product_id,
        action:"list"
    },
    function(data) {
        if (data != 1) {
            alert("Product listing failed; updated " + data + " rows.");
            return;
        }

        var listType = GetQueryStringValue("a");
        // If showing unlisted or archived products, remove the listed product from the list
        if (listType == "unlisted" || listType == "archived") {
            $("#Y" + product_id).remove();
        }
        else if ($("#list" + product_id).length) {   // if showing listed or all products, update the listed status in the table
            $("#list" + product_id).html("\
                    <input type=\'image\' class=\'list_check\' src=\''.DIR_GRAPHICS.'checkmark.png\' onclick=\'UnlistProduct(" + product_id + "); return false;\'>\
                    <span class=\'conf_status1\'>Listed for retail</span><br/>\
                    <a class=\'basket_control\' href=\'#\' onclick=\'UnlistProduct(" + product_id + "); return false;\'>Unlist product</a>\
            ");
        }
    });
}

function UnlistProduct(product_id) {
    jQuery.post("'.PATH.'ajax/product_update_listed_status.php", 
    {
        product_id:product_id,
        action:"unlist"
    },
    function(data) {
        if (data != 1) {
            alert("Product unlisting failed; updated " + data + " rows.");
            return;
        }

        var listType = GetQueryStringValue("a");
        // If showing unlisted or archived products, remove the listed product from the list
        if (listType == "retail" || listType == "archived") {
            $("#Y" + product_id).remove();
        }
        else if ($("#list" + product_id).length) {   // if showing listed or all products, update the listed status in the table
            $("#list" + product_id).html("\
                    <input type=\'image\' class=\'list_check\' src=\''.DIR_GRAPHICS.'checkmark_x.png\' onclick=\'ListProduct(" + product_id + "); return false;\'>\
                    <span class=\'conf_status0\'>Unlisted</span><br/>\
                    <a class=\'basket_control\' href=\'#\' onclick=\'ListProduct(" + product_id + "); return false;\'>List for retail</a>\
            ");
        }
    });
}

var add_to_cart_array = [];
function AddToCart (product_id, product_version, action) {
  var elem;
  var message = "";
  if (elem = document.getElementById("message"+product_id)) message = elem.value;
  var member_id = "";
  if (elem = document.getElementById("member_id")) member_id = elem.value;
  var delivery_id = "'.$delivery_id.'";
  if (elem = document.getElementById("delivery_id")) delivery_id = elem.value;
  jQuery.post("'.PATH.'ajax/'.
// Combined customer_list and customer_basket ajax pages since they were the same 
($template_type == "customer_basket" ? "customer_list" : $template_type).'.php", {
    product_id:product_id,
    product_version:product_version,
    action:action,
    message:message,
    member_id:member_id,
    delivery_id:delivery_id,
    is_bulk:'.($show_bulk ? '1' : '0').'
    },
  function(data) {
    // If site is being inferred from a prior order, then notify of the assumption
    if (data.substr(0,16) == "site_id reverted") {
      popup_src(\'select_delivery_popup.php?first_call=true\', \'select_delivery\', \'\');
      // Set the requested product information so we can re-request it after the basket is handled
      add_to_cart_array = [product_id, product_version, action];
      return false;
      }
    // If no site can be determined, then popup a window to set it.
    if (data == "site_id not set") {
      popup_src(\'select_delivery_popup.php?first_call=true\', \'select_delivery\', \'\');
      // Set the requested product information so we can re-request it after the basket is handled
      add_to_cart_array = [product_id, product_version, action];
      return false;
      }
    var returned_array = data.split(":");
    var new_quantity = returned_array[0];
    var new_inventory = returned_array[1];
    var checked_out = returned_array[2];
    var alert_text = returned_array[3];
    if (document.getElementById("basket_qty" + product_id + "X" + product_version))
    {
        document.getElementById("basket_qty" + product_id + "X" + product_version).innerHTML = new_quantity;
    }
    // Update the number available
    if (document.getElementById("available" + product_id + "X" + product_version))
    {
        document.getElementById("available" + product_id + "X" + product_version).innerHTML = new_inventory;
    }
    // Show/hide the basket controls
    if (new_quantity > 0 && document.getElementById("add" + product_id + "X" + product_version)) // The item is in the basket
    {
        if (document.getElementById("available" + product_id + "X" + product_version) && new_inventory == 0)
        {
            document.getElementById("add" + product_id + "X" + product_version).style.display = "none";
        }
        else
        {
            document.getElementById("add" + product_id + "X" + product_version).style.display = "";
        }
        document.getElementById("sub" + product_id + "X" + product_version).style.display = "";
        document.getElementById("basket_empty" + product_id + "X" + product_version).style.display = "none";
        document.getElementById("basket_full" + product_id + "X" + product_version).style.display = "";
        document.getElementById("in_basket" + product_id + "X" + product_version).style.display = "";
        if (elem = document.getElementById("message_area" + product_id + "X" + product_version)) elem.style.display = "";
    }
    else if (document.getElementById("add" + product_id + "X" + product_version) || document.getElementById("sub" + product_id + "X" + product_version)) // The item is not in the basket
    {
        document.getElementById("add" + product_id + "X" + product_version).style.display = "none";
        document.getElementById("sub" + product_id + "X" + product_version).style.display = "none";
        document.getElementById("basket_empty" + product_id + "X" + product_version).style.display = "";
        document.getElementById("basket_full" + product_id + "X" + product_version).style.display = "none";
        document.getElementById("in_basket" + product_id + "X" + product_version).style.display = "none";
        if (elem = document.getElementById("message_area" + product_id + "X" + product_version)) elem.style.display = "none";
    }
    if (checked_out == 1) {
      document.getElementById("checkout" + product_id + "X" + product_version).innerHTML = "<input type=\"image\"class=\"checkout_check\" src=\"'.DIR_GRAPHICS.'checkout-ccs.png\" onclick=\"AddToCart("+product_id+","+product_version+",\'no_checkout\'); return false;\"><span class=\"checkout_text\">Ordered!</span>";
      document.getElementById("message_button" + product_id + "X" + product_version).innerHTML = "";
      document.getElementById("activity" + product_id + "X" + product_version).innerHTML = "";
    }

    // Update the basket item count
    if ($("#header_basket_quantity'.($show_bulk ? '_bulk' : '').'").length) {
      var headerBasketInfo = $("#header_basket_quantity'.($show_bulk ? '_bulk' : '').'").html();
      var headerBasketAmount = headerBasketInfo.substr(0, headerBasketInfo.indexOf(" "));
      if (action == "add") {
          headerBasketAmount++;
      }
      else if (action == "sub" && headerBasketAmount > 0) {
          headerBasketAmount--;
      }
      $("#header_basket_quantity'.($show_bulk ? '_bulk' : '').'").html(headerBasketAmount + " item" + (headerBasketAmount == 1 ? "" : "s")); 
    }

    if (alert_text && alert_text.length > 1) {
      // Uncomment the following line to show alerts
      alert (alert_text);
    }
    });
}

function SetItem (bpid, action) {
  var elem;
  if (elem = document.getElementById("ship_quantity"+bpid)) var ship_quantity = elem.value;
  if (elem = document.getElementById("weight"+bpid)) var weight = elem.value;
  // Give user indication the function is running
  if (action == "set_quantity") {
    document.getElementById("ship_quantity"+bpid).style.color = "#f80";
    }
  if (action == "set_weight") {
    document.getElementById("weight"+bpid).style.color = "#f80";
    }
  jQuery.post("'.PATH.'ajax/producer_basket.php", {
    bpid:bpid,
    ship_quantity:ship_quantity,
    weight:weight,
    action:action
    },
  function(data) {
//alert(data);
    // Function returns [producer_adjusted_cost]:[extra_charge] OR [ERROR:alert_message]
    var returned_array = data.split(":");
    if (returned_array[0] == "ERROR") {
      alert (returned_array[1]);
      }
    else {
      var producer_adjusted_cost = returned_array[0];
      var extra_charge = returned_array[1];
      var shipped = returned_array[2];
      var total_weight = returned_array[3];
      if (elem = document.getElementById("producer_adjusted_cost"+bpid)) elem.innerHTML = producer_adjusted_cost;
      if (elem = document.getElementById("extra_charge"+bpid)) elem.innerHTML = extra_charge;
      }
    if (action == "set_quantity" && (elem = document.getElementById("ship_quantity"+bpid))) {
      elem.style.color = "#000";
      elem.value = shipped;
      // now also set the weight...
      action = "set_weight";
      }
    if (action == "set_weight" && (elem = document.getElementById("weight"+bpid))) {
      elem.style.color = "#000";
      elem.value = total_weight;
      }
    });
  return false;
  }
</script>
';

$csv_link = '
  <!-- <br><a href="'.$_SERVER['REQUEST_URI'].'&csv=true">Download full list as a CSV file</a> -->
  ';

$content_list = ($content_top ? '
    <div id="content_top">
    '.$content_top.'
    </div>' : '').'
  <div class="product_list">
    '.($message ? '<b><font color="#770000">'.$message.'</font></b>' : '').
        $search_display.
        $producer_display.// Only set for pages needing producer info
        $order_cycle_navigation_display.
        $pager_navigation_display.
        $display.
        $pager_navigation_display.
        $csv_link.'
  </div>
';

// $page_title_html = [value set dynamically]
// $page_subtitle_html = [value set dynamically]
// $page_title = [value set dynamically]
// $page_tab = [value set dynamically]

if($_GET['output'] == 'csv')
{
    header('Content-Type: text/csv');
    header('Content-disposition: attachment;filename=Product_List.csv');
    echo $display;
}
elseif($_GET['output'] == 'pdf')
{
    // DISPLAY NOTHING
}
else
{
    include("template_header.php");
    echo '
      <!-- CONTENT BEGINS HERE -->'.
    $content_list.'
      <!-- CONTENT ENDS HERE -->';
    include("template_footer.php");
}
