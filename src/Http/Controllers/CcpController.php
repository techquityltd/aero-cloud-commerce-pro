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
    public function categories()
    {
        $tree = Category::with('CategoryLang')->all()->toHierarchy();
    }
}
