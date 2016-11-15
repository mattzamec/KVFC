<?php

/////////////////////////////////////////////////////////////////////////////////////////////////
///                                                                                           ///
///    Static classes to set common "global" values (previously contained in the session)     ///
///                                                                                           ///
/////////////////////////////////////////////////////////////////////////////////////////////////

// ActiveCycle static class stores information about "active", "next", and "new" order cycles (so the name is somewhat misleading).
// The "active" cycle is one most recently opened prior to today, regardless of closing date.
// The "next" cycle is one with the closing date in the nearest future, if it exists; otherwise it is the same as "active" cycle.
//   In general, "next" cycle will be the same as "active" cycle, unless the "active" cycle is already closed and there is another cycle in the database
//   with a future closing date.
// The "new" cycle is used to default values when opening a new cycle. It will be the cycle with the latest closing date in the database, with all dates
//   incremented by DAYS_PER_CYCLE from the configuration table.
// Only regular (not bulk) cycles are considered
class ActiveCycle
{
    // Static properties tracking whether (and for which ID if applicable) each query has been run
    // in order to avoid running it unnecessarily (basic caching)
    private static $active_query_id = '-1';
    private static $next_query_complete = false;
    private static $new_query_complete = false;

    // Active order cycle properties
    private static $delivery_id = false;
    private static $delivery_date = false;
    private static $date_open = false;
    private static $date_closed = false;
    private static $order_fill_deadline = false;
    private static $producer_markdown = false;
    private static $retail_markup = false;
    private static $wholesale_markup = false;

    // Next order cycle propeties
    private static $delivery_id_next = false;
    private static $delivery_date_next = false;
    private static $date_open_next = false;
    private static $date_closed_next = false;
    // NOT USED: private static $order_fill_deadline_next = false;
    private static $producer_markdown_next = false;
    private static $retail_markup_next = false;
    private static $wholesale_markup_next = false;
    private static $using_next = false;

    // New order cycle properties
    private static $delivery_date_new = false;
    private static $date_open_new = false;
    private static $date_closed_new = false;
    private static $order_fill_deadline_new = false;
    private static $msg_all_new = false;
    private static $msg_bottom_new = false;
    private static $coopfee_new = false;
    private static $invoice_price_new = false;
    private static $producer_markdown_new = false;
    private static $retail_markup_new = false;
    private static $wholesale_markup_new = false;

    private static $ordering_window = false;
    private static $producer_update_window = false;

    // Runs a SQL query for the Active Cycle and populates properties
    private static function get_delivery_info ($target_delivery_id)
    {
        // If the query has been run for this ID, there is no need to run it again
        if (self::$active_query_id == $target_delivery_id) 
        {
            debug_print('INFO: SKIPPING QUERY in get_delivery_info; $active_query_id is ' . (empty(self::$active_query_id) ? '<i>empty</i>' : self::$active_query_id) 
                . ' and $target_delivery_id is ' . (empty($target_delivery_id) ? '<i>empty</i>' : $target_delivery_id) . '.', array()); $query_where = '1';
            return;
        }

        global $connection;

        // Set up for pulling only order cycles appropriate to the current customer_type permissions
        // Allow "orderex" direct access to all order cycles
        $customer_type_query = (CurrentMember::auth_type('orderex') ? '1' : '0');
        if (CurrentMember::auth_type('member')) $customer_type_query .= '
            OR customer_type LIKE "%member%"';
        if (CurrentMember::auth_type('institution')) $customer_type_query .= '
            OR customer_type LIKE "%institution%"';

        if (empty($target_delivery_id))   // Use the default (current) delivery_id
        {
            $query_where = 'date_open < "'.date ('Y-m-d H:i:s', time()).'"
            AND ('.$customer_type_query.')';
        }
        else // Use a specific delivery_id
        {
            $query_where = 'delivery_id = "'.mysql_real_escape_string ($target_delivery_id).'"';
        }

        // Run the active cycle query
        debug_print('INFO: RUNNING QUERY in get_delivery_info; $active_query_id is ' . (empty(self::$active_query_id) ? '<i>empty</i>' : self::$active_query_id) 
            . ' and $target_delivery_id is ' . (empty($target_delivery_id) ? '<i>empty</i>' : $target_delivery_id) . '.', array()); $query_where = '1';

        $query = '
        SELECT
            delivery_id,
            delivery_date,
            date_open,
            date_closed,
            order_fill_deadline,
            producer_markdown / 100 AS producer_markdown,
            retail_markup / 100 AS retail_markup,
            wholesale_markup / 100 AS wholesale_markup
        FROM '.TABLE_ORDER_CYCLES.'
        WHERE is_bulk = 0
        AND '.$query_where.'
        /* AND order_fill_deadline > "'.date ('Y-m-d H:i:s', time()).'" */
        ORDER BY date_open DESC
        LIMIT 1';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 730099 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));

        // Set default values in case we returned nothing
        self::$delivery_id = 1;
        if ($row = mysql_fetch_object ($result))
        {
            self::$delivery_id = $row->delivery_id;
            self::$delivery_date = $row->delivery_date;
            self::$date_open = $row->date_open;
            self::$date_closed = $row->date_closed;
            self::$order_fill_deadline = $row->order_fill_deadline;
            self::$producer_markdown = $row->producer_markdown;
            self::$retail_markup = $row->retail_markup;
            self::$wholesale_markup = $row->wholesale_markup;
            self::$ordering_window = (time() > strtotime ($row->date_open) && time() < strtotime ($row->date_closed) ? 'open' : 'closed');
            self::$producer_update_window = (time() > strtotime ($row->date_closed) && time() < strtotime ($row->order_fill_deadline) ? 'open' : 'closed');
        }
        elseif ($target_delivery_id != 0)
        {
            self::$delivery_id = $target_delivery_id;
            self::$delivery_date = self::$date_open = self::$date_closed = self::$order_fill_deadline = '';
            self::$producer_markdown = self::$retail_markup = self::$wholesale_markup = 0;
        }
        
        self::$active_query_id = $target_delivery_id;
    }

    // Following functions mostly act as read-only public properties for the active cycle.
    public static function delivery_id ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$delivery_id;
    }
    public static function delivery_date ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$delivery_date;
    }
    public static function date_open ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$date_open;
    }
    public static function date_closed ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$date_closed;
    }
    public static function order_fill_deadline ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$order_fill_deadline;
    }
    public static function producer_markdown ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$producer_markdown;
    }
    public static function retail_markup ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$retail_markup;
    }
    public static function ordering_window ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$ordering_window;
    }
    public static function producer_update_window ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$producer_update_window;
    }
    public static function wholesale_markup ($target_delivery_id = '')
    {
        self::get_delivery_info ($target_delivery_id);
        return self::$wholesale_markup;
    }

    // Runs a SQL query for the Next Cycle and populates properties
    private static function get_next_delivery_info()
    {
        // If the query has been run, no need to run it again
        if (self::$next_query_complete) 
        {
            return;
        }

        global $connection;
        // Set up for pulling only order cycles appropriate to the current customer_type permissions
        // Allow "orderex" direct access to all order cycles
        $customer_type_query = (CurrentMember::auth_type('orderex') ? '1' : '0');
        if (CurrentMember::auth_type('member')) $customer_type_query .= '
            OR customer_type LIKE "%member%"';
        if (CurrentMember::auth_type('institution')) $customer_type_query .= '
            OR customer_type LIKE "%institution%"';

        // Set the default "where condition" to be the cycle that opened most recently
        // Do not use MySQL NOW() because it does not know about the php timezone directive
        debug_print('INFO: In get_next_delivery_info; running query.', array()); $customer_type_query = '1';
        $now = date ('Y-m-d H:i:s', time());
        $query = '
            (SELECT
                date_open,
                date_closed,
                delivery_date,
                delivery_id,
                producer_markdown / 100 AS producer_markdown,
                retail_markup / 100 AS retail_markup,
                wholesale_markup / 100 AS wholesale_markup,
                1 AS using_next
            FROM '.TABLE_ORDER_CYCLES.'
            WHERE is_bulk = 0
            AND date_closed > "'.$now.'"
            AND ('.$customer_type_query.')
            ORDER BY date_closed ASC
            LIMIT 0, 1)
            UNION
            (SELECT
                date_open,
                date_closed,
                delivery_date,
                delivery_id,
                producer_markdown / 100 AS producer_markdown,
                retail_markup / 100 AS retail_markup,
                wholesale_markup / 100 AS wholesale_markup,
                0 AS using_next
            FROM '.TABLE_ORDER_CYCLES.'
            WHERE is_bulk = 0
            AND date_open < "'.$now.'"
            AND ('.$customer_type_query.')
            ORDER BY date_open DESC
            LIMIT 0, 1)';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 863024 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
        if ($row = mysql_fetch_object ($result))
        {
            self::$date_open_next = $row->date_open;
            self::$date_closed_next = $row->date_closed;
            self::$delivery_date_next = $row->delivery_date;
            self::$delivery_id_next = $row->delivery_id;
            self::$producer_markdown_next = $row->producer_markdown;
            self::$retail_markup_next = $row->retail_markup;
            self::$wholesale_markup_next = $row->wholesale_markup;
            self::$using_next = $row->using_next;
            self::$next_query_complete = true;
        }
        // Note that there are no default values if nothing is retrieved from the database ...
    }

    // NextDelivery returns delivery information for either the next delivery that
    // will close (in the future) or -- if that does not exist -- then the most recent
    // delivery that opened (in the past) just as with the ActiveCycle class.
    //
    // So, NextDelivery is the same as ActiveCycle EXCEPT if a delivery cycle has
    // already closed and the next one exists in the database, then the next one will
    // be used instead.
    public static function date_open_next ()
    {
        self::get_next_delivery_info ();
        return self::$date_open_next;
    }
    public static function date_closed_next ()
    {
        self::get_next_delivery_info ();
        return self::$date_closed_next;
    }
    public static function delivery_date_next ()
    {
        self::get_next_delivery_info ();
        return self::$delivery_date_next;
    }
    public static function delivery_id_next ()
    {
        self::get_next_delivery_info ();
        return self::$delivery_id_next;
    }
    public static function producer_markdown_next ()
    {
        self::get_next_delivery_info ();
        return self::$producer_markdown_next;
    }
    public static function retail_markup_next ()
    {
        self::get_next_delivery_info ();
        return self::$retail_markup_next;
    }
    public static function wholesale_markup_next ()
    {
        self::get_next_delivery_info ();
        return self::$wholesale_markup_next;
    }
    public static function using_next ()
    {
        self::get_next_delivery_info ();
        return self::$using_next;
    }

    // Runs a SQL query for the New Cycle and populates properties
    private static function get_new_delivery_info ()
    {
        // If the query has been run, no need to run it again
        if (self::$new_query_complete) 
        {
            return;
        }

        global $connection;
        // Set up for pulling only order cycles appropriate to the current customer_type permissions
        // Allow "orderex" direct access to all order cycles
        $customer_type_query = (CurrentMember::auth_type('orderex') ? '1' : '0');
        if (CurrentMember::auth_type('member')) $customer_type_query .= '
            OR customer_type LIKE "%member%"';
        if (CurrentMember::auth_type('institution')) $customer_type_query .= '
            OR customer_type LIKE "%institution%"';

        // Set the default "where condition" to be the cycle that opened most recently
        // Do not use MySQL NOW() because it does not know about the php timezone directive
        // Run the active cycle query
        debug_print('INFO: In get_new_delivery_info; running query.', array()); $customer_type_query = '1';
        $query = '
        SELECT *
        FROM '.TABLE_ORDER_CYCLES.'
        WHERE is_bulk = 0
        AND ('.$customer_type_query.')
        ORDER BY date_open DESC
        LIMIT 1';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 730099 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));

        if ($row = mysql_fetch_object ($result))
        {
            self::$delivery_date_new = date('Y-m-d', strtotime($row->delivery_date . '+' . DAYS_PER_CYCLE . ' days'));
            self::$date_open_new = date('Y-m-d H:i:s', strtotime($row->date_open . '+' . DAYS_PER_CYCLE . ' days'));
            self::$date_closed_new = date('Y-m-d H:i:s', strtotime($row->date_closed . '+' . DAYS_PER_CYCLE . ' days'));
            self::$order_fill_deadline_new = date('Y-m-d H:i:s', strtotime($row->order_fill_deadline . '+' . DAYS_PER_CYCLE . ' days'));
            self::$msg_all_new = $row->msg_all;
            self::$msg_bottom_new = $row->msg_bottom;
            self::$coopfee_new = $row->coopfee;
            self::$invoice_price_new = $row->invoice_price;
            self::$producer_markdown_new = $row->producer_markdown / 100;
            self::$retail_markup_new = $row->retail_markup / 100;
            self::$wholesale_markup_new = $row->wholesale_markup / 100;
        }
        else 
        {
            self::$delivery_date_new = date('Y-m-d', strtotime(date("Y-m-d") . '+' . DAYS_PER_CYCLE . ' days'));
            self::$date_open_new = date("Y-m-d H:i:s");
            self::$date_closed_new = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            self::$order_fill_deadline_new = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            self::$msg_all_new = '';
            self::$msg_bottom_new = 'Thanks for supporting your local producers!';
            self::$coopfee_new = 
                self::$invoice_price_new = 
                self::$producer_markdown_new = 
                self::$retail_markup_new = 
                self::$wholesale_markup_new = 0;
        }
        self::$new_query_complete = true;
    }

    // Following functions mostly act as read-only public properties for the new cycle.
    public static function delivery_date_new()
    {
        self::get_new_delivery_info();
        return self::$delivery_date_new;
    }
    public static function date_open_new()
    {
        self::get_new_delivery_info();
        return self::$date_open_new;
    }
    public static function date_closed_new()
    {
        self::get_new_delivery_info();
        return self::$date_closed_new;
    }
    public static function order_fill_deadline_new()
    {
        self::get_new_delivery_info();
        return self::$order_fill_deadline_new;
    }
    public static function msg_all_new()
    {
        self::get_new_delivery_info();
        return self::$msg_all_new;
    }
    public static function msg_bottom_new()
    {
        self::get_new_delivery_info();
        return self::$msg_bottom_new;
    }
    public static function coopfee_new()
    {
        self::get_new_delivery_info();
        return self::$coopfee_new;
    }
    public static function invoice_price_new()
    {
        self::get_new_delivery_info();
        return self::$invoice_price_new;
    }
    public static function producer_markdown_new()
    {
        self::get_new_delivery_info();
        return self::$producer_markdown_new;
    }
    public static function retail_markup_new()
    {
        self::get_new_delivery_info();
        return self::$retail_markup_new;
    }
    public static function wholesale_markup_new()
    {
        self::get_new_delivery_info();
        return self::$wholesale_markup_new;
    }

    // Invalidates all queries and forces them to be re-run on next get
    public static function invalidate()
    {
        self::$active_query_id = '-1';
        self::$next_query_complete = self::$new_query_complete = false;
    }
}

// TODO: Sure would be nice to attempt some sort of inheritance here.

// BulkCycle static class stores information about "open", "next", and "new" BULK order cycles.
// The "open" bulk cycle is one that is CURRENTLY OPEN (note that this is different from ActiveCycle, where an active regular cycle could be closed).
//   If there is no open bulk cycle, BulkCycle::delivery_id_open will be false.
// The "next" bulk cycle is one with both open and close dates in the nearest future, if it exists. Again, this is different than ActiveCycle.
//   If there is no next bulk cycle, BulkCycle::delivery_id_next will be false.
// The "new" bulk cycle is used to default values when opening a new bulk cycle. It will be the cycle with the latest closing date in the database, with all dates
//   incremented by DAYS_PER_CYCLE from the configuration table.
// Only bulk (not regular) cycles are considered
class BulkCycle
{
    // Static properties tracking whether each query has been run in order to avoid running them unnecessarily (basic caching)
    private static $open_query_complete = false;
    private static $next_query_complete = false;
    private static $new_query_complete = false;

    // Open bulk order cycle properties
    private static $delivery_id_open = false;
    private static $delivery_date_open = false;
    private static $date_open_open = false;
    private static $date_closed_open = false;
    private static $order_fill_deadline_open = false;
    private static $retail_markup_open = false;

    // Next bulk order cycle propeties
    private static $delivery_id_next = false;
    private static $delivery_date_next = false;
    private static $date_open_next = false;
    private static $date_closed_next = false;
    private static $order_fill_deadline_next = false;
    private static $retail_markup_next = false;

    // New bulk order cycle properties
    private static $delivery_date_new = false;
    private static $date_open_new = false;
    private static $date_closed_new = false;
    private static $order_fill_deadline_new = false;
    private static $msg_all_new = false;
    private static $msg_bottom_new = false;
    private static $coopfee_new = false;
    private static $invoice_price_new = false;
    private static $producer_markdown_new = false;
    private static $retail_markup_new = false;
    private static $wholesale_markup_new = false;

    // Runs a SQL query for the open bulk cycle and populates properties
    private static function get_delivery_info_open ()
    {
        // If the query has been run, there is no need to run it again
        if (self::$open_query_complete) 
        {
            return;
        }

        global $connection;

        // Set up for pulling only order cycles appropriate to the current customer_type permissions
        // Allow "bulk_admin" direct access to all order cycles
        $customer_type_query = (CurrentMember::auth_type('bulk_admin') ? '1' : '0');
        if (CurrentMember::auth_type('member')) $customer_type_query .= '
            OR customer_type LIKE "%member%"';
        if (CurrentMember::auth_type('institution')) $customer_type_query .= '
            OR customer_type LIKE "%institution%"';

        // Run the open cycle query
        debug_print('INFO: In bulk get_delivery_info; running query.', array());

        $now = date('Y-m-d H:i:s', time());
        $query = '
        SELECT
            delivery_id,
            delivery_date,
            date_open,
            date_closed,
            order_fill_deadline,
            retail_markup / 100 AS retail_markup
        FROM '.TABLE_ORDER_CYCLES.'
        WHERE is_bulk = 1
        AND date_open <= "'.$now.'"
        AND date_closed >= "'.$now.'"
        ORDER BY date_open DESC
        LIMIT 1';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 730099 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));

        if ($row = mysql_fetch_object ($result))
        {
            self::$delivery_id_open = $row->delivery_id;
            self::$delivery_date_open = $row->delivery_date;
            self::$date_open_open = $row->date_open;
            self::$date_closed_open = $row->date_closed;
            self::$order_fill_deadline_open = $row->order_fill_deadline;
            self::$retail_markup_open = $row->retail_markup;
        }
        else
        {
            self::$delivery_id_open = 0;
            self::$delivery_date_open = self::$date_open_open = self::$date_closed_open = self::$order_fill_deadline_open = '';
            self::$retail_markup_open = 0;
        }
        self::$open_query_complete = true;
    }

    // Following functions mostly act as read-only public properties for the open bulk cycle.
    public static function delivery_id_open()
    {
        self::get_delivery_info_open();
        return self::$delivery_id_open;
    }
    public static function delivery_date_open()
    {
        self::get_delivery_info_open();
        return self::$delivery_date_open;
    }
    public static function date_open_open()
    {
        self::get_delivery_info_open();
        return self::$date_open_open;
    }
    public static function date_closed_open()
    {
        self::get_delivery_info_open();
        return self::$date_closed_open;
    }
    public static function order_fill_deadline_open()
    {
        self::get_delivery_info_open();
        return self::$order_fill_deadline_open;
    }
    public static function retail_markup_open()
    {
        self::get_delivery_info_open();
        return self::$retail_markup_open;
    }

    // Runs a SQL query for the next bulk cycle and populates properties
    private static function get_delivery_info_next()
    {
        // If the query has been run, no need to run it again
        if (self::$next_query_complete) 
        {
            return;
        }

        global $connection;
        // Set up for pulling only order cycles appropriate to the current customer_type permissions
        // Allow "bulk_admin" direct access to all order cycles
        $customer_type_query = (CurrentMember::auth_type('bulk_admin') ? '1' : '0');
        if (CurrentMember::auth_type('member')) $customer_type_query .= '
            OR customer_type LIKE "%member%"';
        if (CurrentMember::auth_type('institution')) $customer_type_query .= '
            OR customer_type LIKE "%institution%"';

        debug_print('INFO: In bulk get_next_delivery_info; running query.', array());

        $query = '
        SELECT
            delivery_id,
            delivery_date,
            date_open,
            date_closed,
            order_fill_deadline,
            retail_markup / 100 AS retail_markup
        FROM '.TABLE_ORDER_CYCLES.'
        WHERE is_bulk = 1
        AND date_open >= "'.date('Y-m-d H:i:s', time()).'"
        AND date_closed >= date_open
        ORDER BY date_open DESC
        LIMIT 1';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 863024 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
        if ($row = mysql_fetch_object ($result))
        {
            self::$delivery_id_next = $row->delivery_id;
            self::$delivery_date_next = $row->delivery_date;
            self::$date_open_next = $row->date_open;
            self::$date_closed_next = $row->date_closed;
            self::$order_fill_deadline_next = $row->order_fill_deadline;
            self::$retail_markup_next = $row->retail_markup;
        }
        else
        {
            self::$delivery_id_next = 0;
            self::$delivery_date_next = self::$date_open_next = self::$date_closed_next = self::$order_fill_deadline_next = '';
            self::$retail_markup_next = 0;
        }
        self::$next_query_complete = true;
    }

    // Following functions mostly act as read-only public properties for the next bulk cycle.
    public static function delivery_id_next()
    {
        self::get_delivery_info_next();
        return self::$delivery_id_next;
    }
    public static function delivery_date_next()
    {
        self::get_delivery_info_next();
        return self::$delivery_date_next;
    }
    public static function date_open_next()
    {
        self::get_delivery_info_next();
        return self::$date_open_next;
    }
    public static function date_closed_next()
    {
        self::get_delivery_info_next();
        return self::$date_closed_next;
    }
    public static function order_fill_deadline_next()
    {
        self::get_delivery_info_next();
        return self::$order_fill_deadline_next;
    }
    public static function retail_markup_next()
    {
        self::get_delivery_info_next();
        return self::$retail_markup_next;
    }

    // Runs a SQL query for the new bulk cycle and populates properties
    private static function get_new_delivery_info ()
    {
        // If the query has been run, no need to run it again
        if (self::$new_query_complete) 
        {
            return;
        }

        global $connection;
        // Set up for pulling only order cycles appropriate to the current customer_type permissions
        // Allow "bulk_admin" direct access to all order cycles
        $customer_type_query = (CurrentMember::auth_type('bulk_admin') ? '1' : '0');
        if (CurrentMember::auth_type('member')) $customer_type_query .= '
            OR customer_type LIKE "%member%"';
        if (CurrentMember::auth_type('institution')) $customer_type_query .= '
            OR customer_type LIKE "%institution%"';

        debug_print('INFO: In bulk get_new_delivery_info; running query.', array());

        $query = '
        SELECT *
        FROM '.TABLE_ORDER_CYCLES.'
        WHERE is_bulk = 1
        AND ('.$customer_type_query.')
        ORDER BY date_open DESC
        LIMIT 1';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 730099 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));

        if ($row = mysql_fetch_object ($result))
        {
            self::$delivery_date_new = date('Y-m-d', strtotime($row->delivery_date . '+' . DAYS_PER_CYCLE . ' days'));
            self::$date_open_new = date('Y-m-d H:i:s', strtotime($row->date_open . '+' . DAYS_PER_CYCLE . ' days'));
            self::$date_closed_new = date('Y-m-d H:i:s', strtotime($row->date_closed . '+' . DAYS_PER_CYCLE . ' days'));
            self::$order_fill_deadline_new = date('Y-m-d H:i:s', strtotime($row->order_fill_deadline . '+' . DAYS_PER_CYCLE . ' days'));
            self::$msg_all_new = $row->msg_all;
            self::$msg_bottom_new = $row->msg_bottom;
            self::$coopfee_new = $row->coopfee;
            self::$invoice_price_new = $row->invoice_price;
            self::$producer_markdown_new = $row->producer_markdown / 100;
            self::$retail_markup_new = $row->retail_markup / 100;
            self::$wholesale_markup_new = $row->wholesale_markup / 100;
        }
        else 
        {
            self::$delivery_date_new = date('Y-m-d', strtotime(date("Y-m-d") . '+' . DAYS_PER_CYCLE . ' days'));
            self::$date_open_new = date("Y-m-d H:i:s");
            self::$date_closed_new = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            self::$order_fill_deadline_new = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            self::$msg_all_new = self::$msg_bottom_new = '';
            self::$coopfee_new = 
                self::$invoice_price_new = 
                self::$producer_markdown_new = 
                self::$retail_markup_new = 
                self::$wholesale_markup_new = 0;
        }
        self::$new_query_complete = true;
    }

    // Following functions mostly act as read-only public properties for the new cycle.
    public static function delivery_date_new()
    {
        self::get_new_delivery_info();
        return self::$delivery_date_new;
    }
    public static function date_open_new()
    {
        self::get_new_delivery_info();
        return self::$date_open_new;
    }
    public static function date_closed_new()
    {
        self::get_new_delivery_info();
        return self::$date_closed_new;
    }
    public static function order_fill_deadline_new()
    {
        self::get_new_delivery_info();
        return self::$order_fill_deadline_new;
    }
    public static function msg_all_new()
    {
        self::get_new_delivery_info();
        return self::$msg_all_new;
    }
    public static function msg_bottom_new()
    {
        self::get_new_delivery_info();
        return self::$msg_bottom_new;
    }
    public static function coopfee_new()
    {
        self::get_new_delivery_info();
        return self::$coopfee_new;
    }
    public static function invoice_price_new()
    {
        self::get_new_delivery_info();
        return self::$invoice_price_new;
    }
    public static function producer_markdown_new()
    {
        self::get_new_delivery_info();
        return self::$producer_markdown_new;
    }
    public static function retail_markup_new()
    {
        self::get_new_delivery_info();
        return self::$retail_markup_new;
    }
    public static function wholesale_markup_new()
    {
        self::get_new_delivery_info();
        return self::$wholesale_markup_new;
    }

    // Invalidates all queries and forces them to be re-run on next get
    public static function invalidate()
    {
        self::$open_query_complete = self::$next_query_complete = self::$new_query_complete = false;
    }
}

class CurrentBasket
  {
    private static $query_complete = false;
    private static $basket_id = false;
    private static $basket_checked_out = false;
    private static $site_id = false;
    private static $site_short = false;
    private static $site_long = false;
    private static function get_basket_info ()
      {
        if (self::$query_complete === false)
          {
            global $connection;
            $query = '
              SELECT
                '.NEW_TABLE_BASKETS.'.basket_id,
                '.NEW_TABLE_SITES.'.site_id,
                '.NEW_TABLE_SITES.'.site_short,
                '.NEW_TABLE_SITES.'.site_long,
                '.NEW_TABLE_BASKETS.'.checked_out
              FROM
                '.NEW_TABLE_BASKETS.'
              LEFT JOIN '.NEW_TABLE_SITES.' USING(site_id)
              WHERE
                '.NEW_TABLE_BASKETS.'.delivery_id = "'.mysql_real_escape_string (ActiveCycle::delivery_id ()).'"
                AND '.NEW_TABLE_BASKETS.'.member_id = "'.mysql_real_escape_string ($_SESSION['member_id']).'"';
            $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 783032 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
            if ($row = mysql_fetch_object ($result))
              {
                self::$basket_id = $row->basket_id;
                self::$site_id = $row->site_id;
                self::$site_short = $row->site_short;
                self::$site_long = $row->site_long;
                self::$basket_checked_out = $row->checked_out;
                self::$query_complete = true;
              }
          }
      }
    public static function basket_id ()
      {
        self::get_basket_info ();
        return self::$basket_id;
      }
    public static function site_id ()
      {
        self::get_basket_info ();
        return self::$site_id;
      }
    public static function site_short ()
      {
        self::get_basket_info ();
        return self::$site_short;
      }
    public static function site_long ()
      {
        self::get_basket_info ();
        return self::$site_long;
      }
    public static function basket_checked_out ()
      {
        self::get_basket_info ();
        return self::$basket_checked_out;
      }
  }

class CurrentMember
  {
    private static $query_complete = false;
    private static $pending = false;
    private static $username = false;
    private static $auth_type = false;
    private static $business_name = false;
    private static $first_name = false;
    private static $last_name = false;
    private static $first_name_2 = false;
    private static $last_name_2 = false;
    private static function get_member_info ()
      {
        if (self::$query_complete === false)
          {
            global $connection;
            $query = '
              SELECT
                pending,
                username,
                auth_type,
                business_name,
                first_name,
                last_name,
                first_name_2,
                last_name_2
              FROM
                '.TABLE_MEMBER.'
              WHERE
                member_id = "'.mysql_real_escape_string ($_SESSION['member_id']).'"';
            $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 683243 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
            if ($row = mysql_fetch_object ($result))
              {
                self::$pending = $row->pending;
                self::$username = $row->username;
                self::$auth_type = array ();
                self::$auth_type = explode (',', $row->auth_type);
                self::$business_name = $row->business_name;
                self::$first_name = $row->first_name;
                self::$last_name = $row->last_name;
                self::$first_name_2 = $row->first_name_2;
                self::$last_name_2 = $row->last_name_2;
                self::$query_complete = true;
              }
          }
      }
    public static function pending ()
      {
        self::get_member_info ();
        return self::$pending;
      }
    public static function username ()
      {
        self::get_member_info ();
        return self::$username;
      }
    public static function auth_type ($test_auth)
      {
        self::get_member_info ();
        foreach (explode (',', $test_auth) as $needle)
          {
            if (is_array (self::$auth_type) && in_array ($needle, self::$auth_type))
              return true;
          }
          return false;
      }
    public static function business_name ()
      {
        self::get_member_info ();
        return self::$business_name;
      }
    public static function first_name ()
      {
        self::get_member_info ();
        return self::$first_name;
      }
    public static function last_name ()
      {
        self::get_member_info ();
        return self::$last_name;
      }
    public static function first_name_2 ()
      {
        self::get_member_info ();
        return self::$first_name_2;
      }
    public static function last_name_2 ()
      {
        self::get_member_info ();
        return self::$last_name_2;
      }
    public static function clear_member_info ()
      {
        self::get_member_info ();
        self::$pending = false;
        self::$username = false;
        self::$auth_type = false;
        self::$business_name = false;
        self::$first_name = false;
        self::$last_name = false;
        self::$first_name_2 = false;
        self::$last_name_2 = false;
        self::$query_complete = false;
      }
  }
