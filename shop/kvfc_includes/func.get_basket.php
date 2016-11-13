<?php
// If a basket exists for this order, the subroutine returns useful basket information.
// Call with:    get_basket ($member_id, $delivery_id)
//            OR get_basket ($basket_id)
function get_basket($argument1, $argument2 = NULL)
{
    global $connection;

    // If we received two arguments, they are $member_id and $delivery_id
    if (is_numeric ($argument1) && is_numeric ($argument2))
    {
        $query_where = 'WHERE member_id = "'.mysql_real_escape_string($argument1).'"
        AND delivery_id = "'.mysql_real_escape_string ($argument2).'"';
    }
    // and if only one argument, then it is $basket_id
    elseif (is_numeric($argument1))
    {
        $query_where = 'WHERE basket_id = "'.mysql_real_escape_string ($argument1).'"';
    }
    else  // If invalid arguments were passed, we'll return a default basket
    {
        $query_where = 'WHERE basket_id = -1';
    }
    $defaults = array(
      NEW_TABLE_BASKETS.'.basket_id' => -1,
      NEW_TABLE_BASKETS.'.member_id' => -1,
      NEW_TABLE_BASKETS.'.delivery_id' => -1,
      NEW_TABLE_BASKETS.'.site_id' => -1,
      NEW_TABLE_SITES.'.site_short' => '',
      NEW_TABLE_SITES.'.site_long' => '',
      NEW_TABLE_BASKETS.'.delivery_postal_code' => '',
      NEW_TABLE_BASKETS.'.delivery_type' => '',
      NEW_TABLE_BASKETS.'.delivery_cost' => 0.00,
      NEW_TABLE_BASKETS.'.order_cost' => 0.00,
      NEW_TABLE_BASKETS.'.order_cost_type' => 'fixed',
      NEW_TABLE_BASKETS.'.customer_fee_percent' => 0.00,
      NEW_TABLE_BASKETS.'.order_date' => '',
      NEW_TABLE_BASKETS.'.checked_out' => 0,
      NEW_TABLE_BASKETS.'.locked' => 0,
      );
    // Get the basket information...
    $query = '
      SELECT
        '.implode (",\n        ", array_keys($defaults)).'
      FROM '.NEW_TABLE_BASKETS.'
      LEFT JOIN '.NEW_TABLE_SITES.' USING(site_id)
      '.$query_where;
    $result = mysql_query($query, $connection) or die(debug_print ("ERROR: 892305 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    if ($row = mysql_fetch_array($result))
    {
        return $row;
    }
    else
    {
        foreach ($defaults as $key => $value)
        {
            $full_column = explode(".", $key);
            $defaults[$full_column[1]] = $value;
            unset($defaults[$key]);
        }
        return $defaults;
    }
  }
?>