<?php
include_once 'config_openfood.php';
session_start();
valid_auth('orderex,site_admin,bulk_admin');

$active_cycle = new ActiveCycle();
$display_admin .= '
  <table width="100%" class="compact">
    <tr valign="top">
      <td align="left" width="50%">
        <img src="'.DIR_GRAPHICS.'admin.png" width="32" height="32" align="left" hspace="2" alt="Admin Maintenance"><br>
        <b>Admin Maintenance</b>
        <ul class="fancyList1">
            <li><a href="#" onclick="popup_src(\'edit_order_schedule.php\', \'edit_order\', \'view_order_schedule.php\');">Start a New Order Cycle</a></li>
            <li class="last_of_group"><a href="view_order_schedule.php">View/Set Ordering Schedule</a></li>
            '.(CurrentMember::auth_type('bulk_admin') ? '<li class="last_of_group"><a href="view_order_schedule.php?show_bulk=1">View/Set Bulk Ordering Schedule</a></li>
            ': '').'
            <li><a href="category_list_edit.php">Edit Categories and Subcategories</a></li>
            <li><a href="invoice_edittext.php">Edit Invoice Messages</a></li>
        </ul>
      </td>
      <td align="left" width="50%">
        <img src="'.DIR_GRAPHICS.'launch.png" width="32" height="32" align="left" hspace="2" alt="Current Delivery Cycle Functions"><br>
        <b>Current Delivery Cycle Functions</b>
        <ul class="fancyList1">
          <li><a href="orders_list_withtotals.php?delivery_id='.$active_cycle->delivery_id().'">Members with orders this cycle (with totals)</a></li>
          <li><a href="members_list_emailorders.php?delivery_id='.$active_cycle->delivery_id().'">Customer Email Addresses this cycle</a></li>
          <li class="last_of_group"><a href="orders_prdcr_list.php?delivery_id='.$active_cycle->delivery_id().'">Producers with Customers this Cycle</a></li>
        </ul>
        <img src="'.DIR_GRAPHICS.'kcron.png" width="32" height="32" align="left" hspace="2" alt="Previous Delivery Cycle Functions"><br>
        <b>Previous Delivery Cycle Functions</b>
        <ul class="fancyList1">
          <li class="last_of_group"><a href="generate_invoices.php">Generate Invoices</a></li>
        </ul>
      </td>
    </tr>
  </table>';

$page_title_html = '<span class="title">'.$_SESSION['show_name'].'</span>';
$page_subtitle_html = '<span class="subtitle">Order Admin Panel</span>';
$page_title = 'Order Admin Panel';
$page_tab = 'order_admin_panel';

include("template_header.php");
echo '
  <!-- CONTENT BEGINS HERE -->
  '.$display_admin.'
  <!-- CONTENT ENDS HERE -->';
include("template_footer.php");
