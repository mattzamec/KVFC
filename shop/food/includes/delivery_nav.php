<?php

include_once (__DIR__.'/config_openfood.php');

// Helper function to return a prior/next delivery navigation element
function delivery_nav ($delivery_id, $base_url, $querystring, $use_bulk)
{
    // Get the right order cycle. If ID was passed in, use it, otherwise use the active regular or bulk cycle
    $order_cycle = isset($delivery_id) && $delivery_id > 0 
            ? new SpecificCycle($delivery_id) 
            : (isset($use_bulk) && $use_bulk ? new ActiveBulkCycle() : new ActiveCycle());
    
    // If we don't have an order cycle, just return an empty string
    if (!$order_cycle || $order_cycle->delivery_id() <= 0) 
    {
        return '';
    }
    
    return '
    <div id="delivery_id_nav">
        '.($order_cycle->exists_prior() ? '<a class="prior" href="'.$base_url.'?delivery_id='.($order_cycle->delivery_id() - 1).$http_get_query.'">&larr; PRIOR ORDER </a>' : '').'
    <span class="delivery_id">'.date(DATE_FORMAT_CLOSED, strtotime($order_cycle->delivery_date())).'</span>
    '.($order_cycle->exists_next()  ? '<a class="next" href="'.$_SERVER['SCRIPT_NAME'].'?delivery_id='.($order_cycle->delivery_id() + 1).$http_get_query.'"> NEXT ORDER &rarr;</a>' : '').'
    </div>';







    if ($row = mysql_fetch_object($result)) {
        $delivery_div = '  <div id="delivery_id_nav">';

        if ($row->exists_prior) {
            $delivery_div .= '
<a class="prior" href="'.$base_url.($delivery_id - 1).'">&larr; PRIOR CYCLE </a>';
        }
        if ($row->exists_next) {
            $delivery_div .= '
<a class="next" href="'.$base_url.($delivery_id + 1).'"> NEXT CYCLE &rarr;</a>';
        }
        $delivery_div .= '
</div>';
        return $delivery_div;
    }
    else {
        return '';
    }
}

