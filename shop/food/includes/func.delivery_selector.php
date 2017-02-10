<?php

// This function will get the html markup for a div containing past deliveries.
// It is used to select a delivery/order cycle when making/receiving payments.
function delivery_selector ($current_delivery_id, $include_bulk)
{
    global $connection;

    // Get a list of the order cycles in reverse order
    $delivery_info_array = array ();
    $query = '
      SELECT 
        delivery_id,
        date_open,
        date_closed,
        delivery_date,
        is_bulk
      FROM '.TABLE_ORDER_CYCLES.'
      WHERE date_open < NOW()
      ORDER BY delivery_date DESC';
    $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 898034 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
 
    while ($row = mysql_fetch_array($result))
    {
        if ($include_bulk || !$row['is_bulk'])
        {
            $delivery_info_array[$row['delivery_id']]['date_open'] = $row['date_open'];
            $delivery_info_array[$row['delivery_id']]['time_open'] = strtotime($row['date_open']);
            $delivery_info_array[$row['delivery_id']]['date_closed'] = $row['date_closed'];
            $delivery_info_array[$row['delivery_id']]['time_closed'] = strtotime($row['date_closed']);
            $delivery_info_array[$row['delivery_id']]['delivery_date'] = $row['delivery_date'];
            $delivery_info_array[$row['delivery_id']]['is_bulk'] = $row['is_bulk'];
        }
    }

    $list_title = 'Select Delivery Date';
    foreach ($delivery_info_array as $delivery_id => $delivery_info)
    {
        // Check if this is the current delivery
        $current = ($delivery_id == $current_delivery_id);
        if ($current)
        {
            $list_title = 'Selected: '.date('M j, Y', strtotime($delivery_info['delivery_date']));
        }

        // $delivery_attrib[$row['delivery_id']]['time_open'] = strtotime($row['date_open']);
        $day_open = date ('j', $delivery_info['time_open']);
        $month_open = date ('M', $delivery_info['time_open']);
        $year_open = date ('Y', $delivery_info['time_open']);
        $day_closed = date ('j', $delivery_info['time_closed']);
        $month_closed = date ('M', $delivery_info['time_closed']);
        $year_closed = date ('Y', $delivery_info['time_closed']);
        if ($day_open == $day_closed)
        {
            $day_open = '';
        }
        if ($month_open == $month_closed)
        {
            $month_closed = '';
        }
        if ($year_open == $year_closed)
        {
            $year_open = '';
        }

        $list_display .= '
          <li>
            <a class="select_block view'.($current ? ' current' : '').' "href="'.$_SERVER['SCRIPT_NAME'].'?delivery_id='.$delivery_id.'">
              <span class="delivery_date'.($delivery_info['is_bulk'] ? '_bulk' : '').'">'.($delivery_info['is_bulk'] ? 'Bulk ' : '').'Delivery: '.date('M j, Y', strtotime($delivery_info['delivery_date'])).'</span>
              <span class="order_dates">'.$month_open.' '.$day_open.' '.$year_open.' &ndash; '.$month_closed.' '.$day_closed.' '.$year_closed.'</span>
            </a>
          </li>';
      }
    // Display the order cycles and baskets ...
    $display .= '
        <div id="basket_dropdown" class="dropdown" onclick="jQuery(this).toggleClass(\'clicked\')">
          <h1 class="cycle_history">
            '.$list_title.'
          </h1>
          <div id="cycle_history">
            <ul class="cycle_history">'.
              $list_display.'
            </ul>
          </div>
        </div>';
    return $display;
}