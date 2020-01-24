<?php

namespace Techquity\CloudCommercePro\Http\Controllers;

use Aero\Cart\Models\Order;
use Aero\Catalog\Models\Category;



class CcpController
{
    /**
     * Send the given order to veeqo.
     *
     * @param  int  $orderId
     * @return Response
     */
    public function order($orderId)
    {
        $order = Order::findOrFail($orderId);
        CreateOrderJob::dispatch($order);
    }
}
