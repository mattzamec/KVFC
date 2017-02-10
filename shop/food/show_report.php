<?php
include_once 'includes/config_openfood.php';
session_start();

// Items dependent upon the location of this header
$pager = array();

// Set up some variables that might be needed
if (isset($_SESSION['member_id']))
{
    $member_id = $_SESSION['member_id'];
}
if (isset($_SESSION['producer_id_you']))
{
    $producer_id_you = $_SESSION['producer_id_you'];
}

// Allow cashier to override member_id
if (isset ($_GET['member_id']) && CurrentMember::auth_type('cashier'))
{
    $member_id = $_GET['member_id'];
}
// Allow producer_admin or cashier to override producer_id_you
if (isset ($_GET['producer_id']) && CurrentMember::auth_type('cashier,producer_admin'))
{
    $producer_id_you = $_GET['producer_id'];    
}
// Allow anyone to override the delivery_id
$delivery_id = mysql_real_escape_string(isset($_GET['show_bulk']) && $_GET["show_bulk"] == 1 ? (new ActiveBulkCycle())->delivery_id() : (new ActiveCycle())->delivery_id());
if ($_GET['delivery_id']) 
{
  $delivery_id = mysql_real_escape_string ($_GET['delivery_id']);
}

// Initialize display of wholesale and retail to false
$wholesale_member = false;
$retail_member = false;

//////////////////////////////////////////////////////////////////////////////////////
//                                                                                  //
//                         QUERY AND DISPLAY THE DATA                               //
//                                                                                  //
//////////////////////////////////////////////////////////////////////////////////////

// Include the appropriate list "module" from the show_report directory
$report_type = $_GET['type'];
if (!isset($report_type))
{
    $report_type = 'customer_invoice';
}
include_once ('show_report/'.$report_type.'.php');
// Now include the template (specified in the include_file)
include_once ('show_report/'.$report_type.'_template.php');

// Assign some additional unique_data values
$unique_data['major_product'] = $major_product;
$unique_data['major_product_prior'] = $major_product_prior;
$unique_data['minor_product'] = $minor_product;
$unique_data['minor_product_prior'] = $minor_product_prior;
$unique_data['show_major_product'] = $show_major_product;

// Begin with the product results
$result_product = mysql_query($query_product, $connection) or die(debug_print ("ERROR: 752932 ", array ($query_product,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
$number_of_rows = mysql_num_rows($result_product);
$this_row = 0;
// Load the data structure with all the values
while ($row_product = mysql_fetch_array ($result_product))
{
    $product_data[++ $this_row] = (array) $row_product;
}
// Some generalized product_data values
$product_data['row_type'] = $row_type;
$product_data['number_of_rows'] = $number_of_rows;

// Send the page start
$display = open_list_top($product_data, $unique_data);

// Start over and cycle through the data
$this_row = 0;
while ($this_row ++ < $number_of_rows)
{
    $product_data['this_row'] = $this_row;

    // Grab the *unique* producer_fee_percent from the product data (otherwise not available)
    if (isset ($product_data[$this_row]['producer_fee_percent']))
    {
        // NOTE this will give bogus values if the producer_fee_percent is not the same for
        // every product during the ordering cycle
        $unique_data['producer_fee_percent'] = $product_data[$this_row]['producer_fee_percent'];
    }

    $product_data[$this_row]['random_weight_display'] = random_weight_display_calc($product_data, $unique_data);
    $product_data[$this_row]['business_name_display'] = business_name_display_calc($product_data, $unique_data);
    $product_data[$this_row]['pricing_display'] = pricing_display_calc($product_data, $unique_data);
    // $product_data['total_display'] = total_display_calc($product_data);
    $product_data[$this_row]['total_display'] = $product_data['amount'];
    $product_data[$this_row]['inventory_display'] = inventory_display_calc($product_data, $unique_data);

    // New major division
    if ($product_data[$this_row][$major_product] != $product_data[$this_row - 1][$major_product] && $show_major_product)
    {
        if ($listing_is_open)
        {
            $display .= major_product_close($product_data, $unique_data);
            $listing_is_open = 0;
        }
        $display .= major_product_open($product_data, $unique_data);
        // New major division will force a new minor division
        $$minor_product_prior = -1;
    }
    $listing_is_open = 1;
    $display .= show_product_row($product_data, $unique_data);
}

// Close major
if ($show_major_product)
{
    $display .= major_product_close($product_data, $unique_data);
}

// Load the data structure with all the values
$result_adjustment = mysql_query($query_adjustment, $connection) or die(debug_print ("ERROR: 567292 ", array ($query_adjustment,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
$number_of_rows = mysql_num_rows ($result_adjustment);
$this_row = 0;
while ($row_adjustment = mysql_fetch_array ($result_adjustment))
{
    $adjustment_data[++ $this_row] = (array) $row_adjustment;
}

// Start over and cycle through the data
$this_row = 0;
while ($this_row ++ < $number_of_rows)
{
    $adjustment_data['this_row'] = $this_row;
    show_adjustment_row($adjustment_data, $unique_data);
}

// Close the page
$display .= close_list_bottom($product_data, $adjustment_data, $unique_data);

$page_specific_css .= '
<link rel="stylesheet" type="text/css" href="'.PATH.'show_report.css">
<link rel="stylesheet" type="text/css" href="basket_dropdown.css">
<style type="text/css">
#basket_dropdown {
  right:3%;
  }
#content_top {
  margin-bottom:25px;
  }
.pager a {
  width:'.($pager['last_page'] == 0 ? 0 : number_format(72/$pager['last_page'],2)).'%;
  }
.adjustment {
  font-size:80%;
  color:#666;
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
</style>';

$page_specific_javascript .= '
<script type="text/javascript" src="'.PATH.'adjust_ledger.js"></script>';

$content_list = 
  ($content_top ? '
    <div id="content_top">
    '.$content_top.'
    </div>' : '').'
  <div class="show_report">'.
    // $producer_display.
    $display.'
  </div>
';

// $page_title_html = [value set dynamically]
// $page_subtitle_html = [value set dynamically]
// $page_title = [value set dynamically]
// $page_tab = [value set dynamically]

if ($_GET['output'] == 'csv')
{
    header('Content-Type: text/csv');
    header('Content-disposition: attachment;filename=Product_List.csv');
    echo $display;
}
elseif ($_GET['output'] == 'pdf')
{
    // DISPLAY NOTHING
}
else
{
    include("template_header.php");
    echo '
      <!-- CONTENT BEGINS HERE -->
      '.$content_list.'
      <!-- CONTENT ENDS HERE -->';
    include("template_footer.php");
}