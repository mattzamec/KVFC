<?php
// This function will open a new basket and return the basket information in an associative array
// Input data is an associative array with values:
// * member_id         member_id for the basket to be opened
// * delivery_id       delivery_id for the basket to be opened
// * site_id           site_id for the basket to be opened (maybe optional)
// * delivery_type     delivery_type for the basket to be opened (maybe optional)
function open_basket (array $data)
{
    global $connection;
    // Expose additional parameters as they become needed. Call with:
    $basket_fields = array (
      'basket_id',
      'member_id',
      'delivery_id',
      'site_id',
      'delivery_type',
      'delivery_postal_code',
      'delivery_cost',
      'order_cost',
      'order_cost_type',
      'customer_fee_percent',
      'order_date',
      'checked_out',
      'locked'
    );
    // At a minimum, we need to know the member_id and the delivery_id
    if (!$data['member_id'] || !$data['delivery_id'])
    {
        die(debug_print('ERROR: 504 ', 'call to create basket without all parameters', basename(__FILE__).' LINE '.__LINE__));
    }
    // See if a basket already exists
    $query_basket_info = '
      SELECT
        '.implode (",\n        ", $basket_fields).'
      FROM '.NEW_TABLE_BASKETS.'
      WHERE
        member_id = "'.mysql_real_escape_string ($data['member_id']).'"
        AND delivery_id = "'.mysql_real_escape_string ($data['delivery_id']).'"';
    $result_basket_info = mysql_query($query_basket_info, $connection) or die(debug_print ("ERROR: 892122 ", array ($query_basket_info,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    if ($row_basket_info = mysql_fetch_array($result_basket_info))
    {
        // If we have an existing basket, return the info and we're done
        return ($row_basket_info);
    }

    // KVFC only has one site and one delivery type. If ever there are new ones added, we may need to account for this
    // when opening a new basket, and either ensure we have them in the $data array supplied, or select the values
    // for the last legit order for this member or find another way of guessing at them.
    // For the time being, we just brutally hardcode it to 1 and 'P'
    $data['site_id'] = 1;
    $data['delivery_type'] = 'P';

    // Get additional basket data for opening this basket
    $query_basket_data = '
      SELECT
        '.NEW_TABLE_SITES.'.delivery_postal_code,
        '.NEW_TABLE_SITES.'.delivery_charge AS delivery_cost,
        '.NEW_TABLE_SITES.'.site_long,
        '.TABLE_MEMBERSHIP_TYPES.'.order_cost,
        '.TABLE_MEMBERSHIP_TYPES.'.order_cost_type,
        '.TABLE_MEMBER.'.customer_fee_percent,
        '.TABLE_MEMBER.'.zip AS home_zip,
        '.TABLE_MEMBER.'.work_zip
      FROM '.NEW_TABLE_SITES.'
      INNER JOIN '.TABLE_MEMBER.'
      LEFT JOIN '.TABLE_MEMBERSHIP_TYPES.' USING(membership_type_id)
      WHERE
        '.NEW_TABLE_SITES.'.site_id = "'.mysql_real_escape_string($data['site_id']).'" AND '
        .TABLE_MEMBER.'.member_id = "'.mysql_real_escape_string($data['member_id']).'"';
    $result_basket_data = mysql_query($query_basket_data, $connection) or die(debug_print ("ERROR: 983134 ", array ($query_basket_data,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    if ($row_basket_data = mysql_fetch_array($result_basket_data))
    {
        $data['delivery_postal_code'] = $row_basket_data['delivery_postal_code'];
        $data['delivery_cost'] = $row_basket_data['delivery_cost'];
        $data['order_cost'] = $row_basket_data['order_cost'];
        $data['order_cost_type'] = $row_basket_data['order_cost_type'];
        $data['customer_fee_percent'] = $row_basket_data['customer_fee_percent'];
        $data['site_long'] = $row_basket_data['site_long'];
        $home_zip = $row_basket_data['home_zip'];
        $work_zip = $row_basket_data['work_zip'];
    }
    else
    {
        die(debug_print('ERROR: 506 ', 'create basket failure to gather information', basename(__FILE__).' LINE '.__LINE__));
    }
  // If the delivery is not 'P' (pickup) then set the clobber $delivery_postal_code with the correct value
  // However, it WILL be 'P' for all KVFC baskets
  if ($data['delivery_type'] == 'H')
  {
      $data['delivery_postal_code'] = $home_zip;
  }
  elseif ($data['delivery_type'] == 'W')
  {
      $data['delivery_postal_code'] = $work_zip;
  }
  elseif ($data['delivery_type'] != 'P')
  {
      die(debug_print('ERROR: 507 ', 'create basket invalid delivery_type', basename(__FILE__).' LINE '.__LINE__));
  }
  // Check if there is a delivery_postal code provided This should possibly check against
  // the tax_rates table to see that the postal code is included there...?
  // In KVFC, this comes from the single kvfc_sites table entry and will be populated ...
  if (! $data['delivery_postal_code'])
  {
      die(debug_print('ERROR: 508 ', 'create basket invalid delivery_postal_code', basename(__FILE__).' LINE '.__LINE__));
  }
  // Now open a basket with the provided (or guessed) information
  $query_open_basket = '
    INSERT INTO '.NEW_TABLE_BASKETS.'
    SET
      /*basket_id (auto_increment )*/
      member_id = "'.mysql_real_escape_string($data['member_id']).'",
      delivery_id = "'.mysql_real_escape_string($data['delivery_id']).'",
      site_id = "'.mysql_real_escape_string($data['site_id']).'",
      delivery_type = "'.mysql_real_escape_string($data['delivery_type']).'",
      delivery_postal_code = "'.mysql_real_escape_string($data['delivery_postal_code']).'",
      delivery_cost = "'.mysql_real_escape_string($data['delivery_cost']).'",
      order_cost = "'.mysql_real_escape_string($data['order_cost']).'",
      order_cost_type = "'.mysql_real_escape_string($data['order_cost_type']).'",
      customer_fee_percent = "'.mysql_real_escape_string($data['customer_fee_percent']).'",
      /*order_date (timestamp) */
      checked_out = "0",
      locked = "0"';
    $result_open_basket = mysql_query($query_open_basket, $connection) or die(debug_print ("ERROR: 895237 ", array ($query_basket_data,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
    $data['basket_id'] = mysql_insert_id();
    $data['checked_out'] = 0;                  // Manually set rather than queried
    $data['locked'] = 0;                       // Manually set rather than queried
    $data['order_date'] = date("Y-m-d H:i:s"); // Approximate, since it did not come from the database
    return ($data);
  }