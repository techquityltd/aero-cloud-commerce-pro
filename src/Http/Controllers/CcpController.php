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
        $return = [];

        $parents = Category::all()->toHierarchy();

        foreach($parents as $parent) {


            foreach($parent->getDescendantsAndSelf() as $descendant) {

                $return[] = (['id' => $descendant->id, 'name' => $descendant->name, 'parent_id' => $descendant->parent_id]);

            } 

        }

        return json_encode($return);

    }

}
