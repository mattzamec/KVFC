<?php
include_once 'includes/config_openfood.php';
session_start();
valid_auth('bulk_admin');

// Configure the delivery_id
if (isset ($_GET['delivery_id'])) $delivery_id = $_GET['delivery_id'];
else $delivery_id = (new ActiveBulkCycle())->delivery_id();

// Sanitize and get directive for sort direction
$switch = '';
$order = '';
$order_arrow = '';
if (isset ($_GET['order']) && $_GET['order'] == 'desc')
{
    $switch = '&amp;order=desc';
    $order = ' DESC';
    $order_arrow = '&darr;';
}
else
{
    $switch = '&amp;order=asc';
    $order = ' ASC';
    $order_arrow = '&uarr;';
}

// Sanitize and get directive for sort parameter
$sort = '';
if (isset($_GET['sort']) && $_GET['sort'] == 'bulk_id')
{
    $sort = 'bulk_id';    
}
elseif (isset($_GET['sort']) && $_GET['sort'] == 'quantity')
{
    $sort = 'quantity';
}
else
{
    $sort = 'product_name';
}

$query = '
SELECT 
    SUM('.NEW_TABLE_BASKET_ITEMS.'.quantity) AS `quantity`,
    '.NEW_TABLE_PRODUCTS.'.product_name,
    '.NEW_TABLE_PRODUCTS.'.ordering_unit,
    '.NEW_TABLE_PRODUCTS.'.unit_price,
    SUBSTRING_INDEX('.NEW_TABLE_PRODUCTS.'.bulk_sku, \'_\', 1) AS `bulk_id`,
    SUBSTRING_INDEX('.NEW_TABLE_PRODUCTS.'.bulk_sku, \'_\', -1) AS `bulk_variant_id`    
FROM '.NEW_TABLE_BASKET_ITEMS.'
JOIN '.NEW_TABLE_BASKETS.' ON '.NEW_TABLE_BASKETS.'.basket_id = '.NEW_TABLE_BASKET_ITEMS.'.basket_id
JOIN '.NEW_TABLE_PRODUCTS.' ON '.NEW_TABLE_BASKET_ITEMS.'.product_id = '.NEW_TABLE_PRODUCTS.'.product_id
AND '.NEW_TABLE_PRODUCTS.'.product_version = '.NEW_TABLE_BASKET_ITEMS.'.product_version
WHERE '.NEW_TABLE_BASKETS.'.delivery_id = 329
GROUP BY 
    '.NEW_TABLE_PRODUCTS.'.product_name,
    '.NEW_TABLE_PRODUCTS.'.ordering_unit,
    '.NEW_TABLE_PRODUCTS.'.unit_price,
    SUBSTRING_INDEX('.NEW_TABLE_PRODUCTS.'.bulk_sku, \'_\', 1),
    SUBSTRING_INDEX('.NEW_TABLE_PRODUCTS.'.bulk_sku, \'_\', -1)
ORDER BY `'.$sort.'` '.$order;

$result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 785035 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
$num_orders = mysql_numrows($result);

while ($row = mysql_fetch_array($result))
{
    $display .= '
  <tr id="'.$row['bulk_id'].'_'.$row['bulk_variant_id'].'">
    <td><strong>'.$row['product_name'].' ('.$row['ordering_unit'].')</strong></td>
    <td>'.number_format($row['unit_price'], 2).'</td>
    <td><strong>'.$row['quantity'].'</strong></td>
    <td>'.$row['bulk_id'].'</td>
    <td>'.$row['bulk_variant_id'].'</td>
  </tr>';
}

$content_list = '
<table id="bulk_orders">
  <tr>
    <th>Product</th>
    <th>Price</th>
    <th>Quantity</th>
    <th>Bulk ID</th>
    <th>Bulk Variant</th>
  </tr>
  '.$display.'
</table>';

$page_specific_css .= '
<style type="text/css">
table td {
    padding: 4px;
}
</style>
';
$page_title_html = '<span class="title">Bulk Order Summary</span>';
$page_subtitle_html = '<span class="subtitle">Bulk Order Summary</span>';
$page_title = 'Bulk Order Summary';
$page_tab = 'order_admin_panel';

include("template_header.php");
echo '
  <!-- CONTENT BEGINS HERE -->
  '.$content_list.'
  <!-- CONTENT ENDS HERE -->';
include("template_footer.php");

