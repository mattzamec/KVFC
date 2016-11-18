<?php

// Base class for more specific order types (Active, Bulk and so on) 
abstract class OrderCycle
{
    // Order cycle properties
    protected $delivery_id = false;
    protected $delivery_date = false;
    protected $date_open = false;
    protected $date_closed = false;
    protected $order_fill_deadline = false;
    protected $msg_all = false;
    protected $msg_bottom = false;
    protected $coopfee = false;
    protected $invoice_price = false;
    protected $producer_markdown = false;
    protected $retail_markup = false;
    protected $wholesale_markup = false;
    protected $is_open_for_ordering = false;
    protected $is_open_for_fulfillment = false;
    protected $exists_next = false;
    protected $exists_prior = false;    

    // Constructor will run the query for the appropriate $prm_where_clause and $prm_order_by supplied
    // and assign properties
    protected function __construct($prm_where_clause, $prm_order_by = 'date_open DESC') 
    {
        global $connection;

        // No need to worry about institution orders for the time being since KVFC does not do wholesale to institutions.
        //$customer_type_query = (CurrentMember::auth_type('orderex') ? '1' : '0');
        //if (CurrentMember::auth_type('member')) $customer_type_query .= '
        //    OR customer_type LIKE "%member%"';
        //if (CurrentMember::auth_type('institution')) $customer_type_query .= '
        //    OR customer_type LIKE "%institution%"';

        $query = '
        SELECT *,
	    IFNULL((SELECT 1 FROM '.TABLE_ORDER_CYCLES.' PriorCycle WHERE PriorCycle.delivery_id = '.TABLE_ORDER_CYCLES.'.delivery_id - 1), 0) AS `exists_prior`,
	    IFNULL((SELECT 1 FROM '.TABLE_ORDER_CYCLES.' NextCycle  WHERE NextCycle.delivery_id  = '.TABLE_ORDER_CYCLES.'.delivery_id + 1), 0) AS `exists_next`
        FROM '.TABLE_ORDER_CYCLES.'
        WHERE '.$prm_where_clause.'
        ORDER BY '.$prm_order_by.'
        LIMIT 1';
        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 730099 ", array ($query, mysql_error()), basename(__FILE__).' LINE '.__LINE__));

        // Run the query
        // debug_print('INFO: RUNNING QUERY ' . $query . ' in OrderCycle constructor for WHERE clause '.$prm_where_clause.'; class is '.get_class($this));

        if ($row = mysql_fetch_object ($result))
        {
            $this->delivery_id = $row->delivery_id;
            $this->delivery_date = $row->delivery_date;
            $this->date_open = $row->date_open;
            $this->date_closed = $row->date_closed;
            $this->order_fill_deadline = $row->order_fill_deadline;
            $this->msg_all = $row->msg_all;
            $this->msg_bottom = $row->msg_bottom;
            $this->coopfee = $row->coopfee;
            $this->invoice_price = $row->invoice_price;
            $this->producer_markdown = $row->producer_markdown / 100;
            $this->retail_markup = $row->retail_markup / 100;
            $this->wholesale_markup = $row->wholesale_markup / 100;
            $this->is_open_for_ordering = (time() > strtotime ($row->date_open) && time() < strtotime ($row->date_closed));
            $this->is_open_for_fulfillment = (time() > strtotime ($row->date_closed) && time() < strtotime ($row->order_fill_deadline));
            $this->exists_next = $row->exists_next;
            $this->exists_prior = $row->exists_prior;
        }
        else
        {    // Set defaults in case nothing was retrieved
            $this->delivery_id = -1;
            $this->delivery_date = 
                $this->date_open = 
                $this->date_closed = 
                $this->order_fill_deadline = 
                $this->msg_all = 
                $this->msg_bottom = '';
            $this->coopfee = 
                $this->invoice_price = 
                $this->producer_markdown = 
                $this->retail_markup = 
                $this->wholesale_markup = 0;
            $this->is_open_for_ordering = 
                $this->is_open_for_fulfillment = 
                $this->exists_next = 
                $this->exists_prior = false;
        }
    }

    // Following functions act as read-only public properties for the cycle.
    public function delivery_id()
    {
        return $this->delivery_id;
    }
    public function delivery_date()
    {
        return $this->delivery_date;
    }
    public function date_open()
    {
        return $this->date_open;
    }
    public function date_closed()
    {
        return $this->date_closed;
    }
    public function order_fill_deadline()
    {
        return $this->order_fill_deadline;
    }
    public function msg_all()
    {
        return $this->msg_all;
    }
    public function msg_bottom()
    {
        return $this->msg_bottom;
    }
    public function coopfee()
    {
        return $this->coopfee;
    }
    public function invoice_price()
    {
        return $this->invoice_price;
    }
    public function producer_markdown()
    {
        return $this->producer_markdown;
    }
    public function retail_markup()
    {
        return $this->retail_markup;
    }
    public function wholesale_markup()
    {
        return $this->wholesale_markup;
    }
    public function is_open_for_ordering()
    {
        return $this->is_open_for_ordering;
    }
    public function is_open_for_fulfillment()
    {
        return $this->is_open_for_fulfillment;
    }
    public function exists_next()
    {
        return $this->exists_next;
    }
    public function exists_prior()
    {
        return $this->exists_prior;
    }
}

// The "active" cycle is one most recently opened prior to today, regardless of closing date.
class ActiveCycle extends OrderCycle
{
    public function __construct() 
    {
        parent::__construct('is_bulk = 0
        AND date_open < "'.date ('Y-m-d H:i:s', time()).'"');
    }
}

// The "next" cycle is one with the closing date in the nearest future, if it exists; otherwise it is the same as "active" cycle.
//   In general, "next" cycle will be the same as "active" cycle, unless the "active" cycle is already closed and there is another cycle in the database
//   with a future closing date.
class NextCycle extends OrderCycle
{
    public function __construct() 
    {
        // First try to get a cycle with a closing date in the future
        parent::__construct('is_bulk = 0
        AND date_closed > "'.date ('Y-m-d H:i:s', time()).'"', 'date_closed ASC');

        // If there isn't one, we use the active cycle
        if ($this->delivery_id == -1) 
        {
            parent::__construct('is_bulk = 0
            AND date_open < "'.date ('Y-m-d H:i:s', time()).'"');
        }
    }
}

// The "new" cycle is used to default values when opening a new cycle. It will be the cycle with the latest opening date in the database, 
//   with all dates incremented by DAYS_PER_CYCLE from the configuration table.
class NewCycle extends OrderCycle
{
    public function __construct() 
    {
        parent::__construct('is_bulk = 0');
        
        if ($this->delivery_id != -1)   // Increment the dates found in the database
        {
            $this->delivery_date = date('Y-m-d', strtotime($this->delivery_date . '+' . DAYS_PER_CYCLE . ' days'));
            $this->date_open = date('Y-m-d H:i:s', strtotime($this->date_open . '+' . DAYS_PER_CYCLE . ' days'));
            $this->date_closed = date('Y-m-d H:i:s', strtotime($this->date_closed . '+' . DAYS_PER_CYCLE . ' days'));
            $this->order_fill_deadline = date('Y-m-d H:i:s', strtotime($this->order_fill_deadline . '+' . DAYS_PER_CYCLE . ' days'));
        }
        else  // Default dates; note that this would only happen for the very first order cycle so it's practically redundant
        {
            $this->delivery_date = date('Y-m-d', strtotime(date("Y-m-d") . '+' . DAYS_PER_CYCLE . ' days'));
            $this->date_open = date("Y-m-d H:i:s");
            $this->date_closed = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            $this->order_fill_deadline = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            $this->msg_all = '';
            $this->msg_bottom = 'Thanks for supporting your local producers!';
        }
    }
}

// The Bulk Cycles mirror the active, next and new queries for regular cycles but include only bulk orders.
class ActiveBulkCycle extends OrderCycle
{
    public function __construct() 
    {
        parent::__construct('is_bulk = 1
        AND date_open < "'.date ('Y-m-d H:i:s', time()).'"');
    }
}
class NextBulkCycle extends OrderCycle
{
    public function __construct() 
    {
        // First try to get a cycle with a closing date in the future
        parent::__construct('is_bulk = 1
        AND date_closed > "'.date ('Y-m-d H:i:s', time()).'"', 'date_closed ASC');

        // If there isn't one, we use the active cycle
        if ($this->delivery_id == -1) 
        {
            parent::__construct('is_bulk = 1
            AND date_open < "'.date ('Y-m-d H:i:s', time()).'"');
        }
    }
}
class NewBulkCycle extends OrderCycle
{
    public function __construct() 
    {
        parent::__construct('is_bulk = 1');
        
        if ($this->delivery_id != -1)   // Increment the dates found in the database
        {
            $this->delivery_date = date('Y-m-d', strtotime($this->delivery_date . '+' . DAYS_PER_CYCLE . ' days'));
            $this->date_open = date('Y-m-d H:i:s', strtotime($this->date_open . '+' . DAYS_PER_CYCLE . ' days'));
            $this->date_closed = date('Y-m-d H:i:s', strtotime($this->date_closed . '+' . DAYS_PER_CYCLE . ' days'));
            $this->order_fill_deadline = date('Y-m-d H:i:s', strtotime($this->order_fill_deadline . '+' . DAYS_PER_CYCLE . ' days'));
        }
        else  // Default dates; note that this would only happen for the very first order cycle so it's practically redundant
        {
            $this->delivery_date = date('Y-m-d', strtotime(date("Y-m-d") . '+' . DAYS_PER_CYCLE . ' days'));
            $this->date_open = date("Y-m-d H:i:s");
            $this->date_closed = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            $this->order_fill_deadline = date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+' . DAYS_PER_CYCLE . ' days'));
            $this->msg_all = '';
            $this->msg_bottom = 'Thanks for supporting your local producers!';
        }
    }
}

// A specific cycle must specify an ID
class SpecificCycle extends OrderCycle
{
    public function __construct($prm_id) 
    {
        // Just to be safe, let's make sure the ID is numeric
        if (!is_numeric($prm_id)) 
        {
            $prm_id = '-1';
        }
        parent::__construct("delivery_id = $prm_id");
    }
}

// Base class for more specific basket types (Current, Bulk) 
abstract class Basket
{
    // Basket properties
    protected $basket_id = false;
    protected $site_id = false;

    // Constructor will run the appropriate query and assign properties
    protected function __construct($prm_is_bulk = false) 
    {
        global $connection;

        $query = '
          SELECT
            '.NEW_TABLE_BASKETS.'.basket_id,
            '.NEW_TABLE_SITES.'.site_id
          FROM '.NEW_TABLE_BASKETS.'
          LEFT JOIN '.NEW_TABLE_SITES.' USING(site_id)
          WHERE '.NEW_TABLE_BASKETS.'.delivery_id = "'.mysql_real_escape_string($prm_is_bulk ? (new ActiveBulkCycle())->delivery_id() : (new ActiveCycle())->delivery_id()).'"
          AND '.NEW_TABLE_BASKETS.'.member_id = "'.mysql_real_escape_string ($_SESSION['member_id']).'"';

        // Run the query
        // debug_print('INFO: RUNNING QUERY '.$query.' in Basket constructor; class is '.get_class($this));

        $result = @mysql_query($query, $connection) or die(debug_print ("ERROR: 783032 ", array ($query,mysql_error()), basename(__FILE__).' LINE '.__LINE__));
        if ($row = mysql_fetch_object ($result))
        {
            $this->basket_id = $row->basket_id;
            $this->site_id = $row->site_id;
        }
        else
        {    // Set defaults in case nothing was retrieved
            $this->basket_id = -1;
            $this->site_id = -1;
        }
    }

    // Following functions act as read-only public properties for the cycle.
    public function basket_id()
    {
        return $this->basket_id;
    }
    public function site_id()
    {
        return $this->site_id;
    }
}

// Current regular basket
class CurrentBasket extends Basket 
{
    public function __construct() 
    {
        parent::__construct(false);
    }
}

// Current bulk basket
class CurrentBulkBasket extends Basket 
{
    public function __construct() 
    {
        parent::__construct(true);
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
        foreach (explode(',', $test_auth) as $needle)
        {
            if(is_array(self::$auth_type) && in_array($needle, self::$auth_type))
            {
                return true;
            }
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