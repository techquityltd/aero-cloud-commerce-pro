<?php

namespace Techquity\CloudCommercePro\Http\Controllers;

use Aero\Cart\Models\Order;
use Aero\Catalog\Models\Category;
use Aero\Catalog\Models\Variant;

class CcpController
{
    /**
     * Category list for CCP.
     *
     *
     * @return Object
     */
    public function categories()
    {
        $return = [];

        $parents = Category::all()->toHierarchy();

        foreach($parents as $parent) {


            foreach($parent->getDescendantsAndSelf() as $descendant) {

                $return[$descendant->id] = (['id' => $descendant->id, 'name' => $descendant->name, 'parent_id' => $descendant->parent_id]);

            }

        }

        return response()->json(($return), JSON_UNESCAPED_UNICODE);

    }


    /**
     * Listings for CCP.
     *
     *
     * @return Object
     */
    public function listings()
    {
        $return = [];

        $variants = Variant::cursor()->filter(function ($variant) {
            return $variant->id;
        });

        foreach ($variants as $variant) {

            if(isset($variant->product)) {

                $return[$variant->id]['id'] = $variant->id;
                $return[$variant->id]['sku'] = $variant->sku;
                $return[$variant->id]['image'] = isset($variant->product->images->first()->file) ? trim(env('APP_URL').'/').($variant->product->images->first()->file) : null;
                $return[$variant->id]['url'] = $variant->product->getUrl(true);
                $return[$variant->id]['name'] = $variant->product->name;

                $inc = setting('prices_inserted_inc_tax');

                $return[$variant->id]['price'] = $inc ? ($variant->getPriceForQuantity(1)->sale_value_inc / 100) : ($variant->getPriceForQuantity(1)->sale_value_ex / 100);

                if(!$inc){
                    $return[$variant->id]['vat_amount'] = (($variant->getPriceForQuantity(1)->sale_value_inc-$variant->getPriceForQuantity(1)->sale_value_ex) / 100);
                }

                $rate = ($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / $variant->getPriceForQuantity(1)->sale_value_ex * 100;

                $return[$variant->id]['vat_rate'] = round($rate);
                $return[$variant->id]['barcode'] = $variant->id;

                $return[$variant->id]['attributes'] = $variant->attributes->map(function($attribute){
                    return [$attribute->group->name => $attribute->name];
                })->values();

                $return[$variant->id]['parent_ref'] = $variant->product->model;
            }


        }
        return response()->json(($return), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Listings for CCP.
     *
     *
     * @return Object
     */
    public function orders()
    {
        $return = [];
        return response()->json(($return), JSON_UNESCAPED_UNICODE);
    }

}
