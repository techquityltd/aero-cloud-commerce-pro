<?php

namespace Techquity\CloudCommercePro\Http\Controllers;

use Aero\Cart\Models\Order;
use Aero\Catalog\Events\ProductUpdated;
use Aero\Catalog\Models\Category;
use Aero\Catalog\Models\Variant;
use Aero\Catalog\Models\Product;
use Illuminate\Support\Arr;

use Illuminate\Http\Request;

class CcpController
{
    /**
     * Update stock API for CCP.
     *
     *
     * @return void
     */
    public function stock(Request $request) : void {

        if ($request->isMethod('post')) {

            $json = json_decode($request->getContent(), true);

            if(is_array($json)) {

                foreach($json as $stock) {

                    if(isset($stock['sku']) && isset($stock['stock']) && $variant = Variant::where('sku', '=', (string)$stock['sku'])->first()) {

                        // Update the stock
                        $variant->stock_level = (int)$stock['stock'];
                        $variant->save();

                        // Trigger updated event
                        $parent = Product::find($variant->product_id);
                        event(new ProductUpdated($parent));
                    }
                }
            }
        }
    }

    public function dispatch() {

    }

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

        foreach (Variant::cursor() as $variant) {

            if(isset($variant->product)) {

                $return[$variant->id]['id'] = $variant->id;
                $return[$variant->id]['sku'] = $variant->sku;
                $return[$variant->id]['image'] = isset($variant->product->images->first()->file) ? trim(env('APP_URL').'/').($variant->product->images->first()->file) : null;
                $return[$variant->id]['url'] = $variant->product->getUrl(true);
                $return[$variant->id]['name'] = $variant->product->name;

                $inc = setting('prices_inserted_inc_tax');

                if($inc) {

                    $return[$variant->id]['price'] = ($variant->getPriceForQuantity(1)->sale_value_inc / 100);

                } else {

                    $return[$variant->id]['price_ex'] = ($variant->getPriceForQuantity(1)->sale_value_ex / 100);
                    $return[$variant->id]['vat_amount'] = (($variant->getPriceForQuantity(1)->sale_value_inc-$variant->getPriceForQuantity(1)->sale_value_ex) / 100);
                }

                $rate = ($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / $variant->getPriceForQuantity(1)->sale_value_ex * 100;

                $return[$variant->id]['vat_rate'] = round($rate);
                $return[$variant->id]['barcode'] = $variant->id;

                $return[$variant->id]['attributes'] = $variant->attributes->map(function($attribute){
                    return ['name' => $attribute->group->name, 'value' => $attribute->name];
                })->values();

                $return[$variant->id]['parent_ref'] = $variant->product->model;

            }
        };

        return response()->json(($return), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Orders for CCP.
     *
     *
     * @return Object
     */
    public function orders()
    {
        $return = [];

        foreach (Order::whereIn('order_status_id', config('aero.cloudcommercepro.order_statuses'))->cursor() as $order) {

            $return[$order->id]['reference'] = $order->reference;
            $return[$order->id]['order_status'] = $order->status->name;
            $return[$order->id]['order_date'] = $order->created_at;

            $return[$order->id]['payments'] = $order->payments->map(function($payment) use ($order) {

                return collect([
                    'status' => $payment->state,
                    'method' => $payment->method->name,
                    'date' => $payment->created_at,

                ])->toArray();
            })->values();


            $return[$order->id]['total'] = ($order->Total + $order->TotalTax) / 100;
            $return[$order->id]['shipping'] = ($order->ShippingRounded) / 100;
            $return[$order->id]['discount'] = ($order->DiscountRounded) / 100;
            $return[$order->id]['currency'] = $order->currency->code;

            $return[$order->id]['shipping_method'] = isset($order->shippingMethod->name) ? $order->shippingMethod->name : null;
            $return[$order->id]['shipping_date'] = null;

            $return[$order->id]['billing_name'] = $order->billingAddress->first_name ." ".$order->billingAddress->last_name;
            $return[$order->id]['billing_address_company'] = $order->billingAddress->company;
            $return[$order->id]['billing_address1'] = $order->billingAddress->line_1;
            $return[$order->id]['billing_address2'] = $order->billingAddress->line_2;

            $return[$order->id]['billing_city'] = $order->billingAddress->city;
            $return[$order->id]['billing_state'] =  $order->billingAddress->zone_name;
            $return[$order->id]['billing_zip'] = $order->billingAddress->postcode;
            $return[$order->id]['billing_country'] = $order->billingAddress->country_code;
            $return[$order->id]['billing_phone'] = $order->billingAddress->phone;
            $return[$order->id]['billing_mobile'] = $order->billingAddress->mobile;
            $return[$order->id]['billing_email'] = $order->email;


            $return[$order->id]['shipping_name'] = $order->shippingAddress->first_name ." ".$order->billingAddress->last_name;
            $return[$order->id]['shipping_address_company'] = $order->shippingAddress->company;
            $return[$order->id]['shipping_address1'] = $order->shippingAddress->line_1;
            $return[$order->id]['shipping_address2'] = $order->shippingAddress->line_2;

            $return[$order->id]['shipping_city'] = $order->shippingAddress->city;
            $return[$order->id]['shipping_state'] = $order->shippingAddress->zone_name;
            $return[$order->id]['shipping_zip'] = $order->shippingAddress->postcode;
            $return[$order->id]['shipping_country'] = $order->shippingAddress->country_code;
            $return[$order->id]['shipping_phone'] = $order->shippingAddress->phone;
            $return[$order->id]['shipping_mobile'] = $order->shippingAddress->mobile;
            $return[$order->id]['shipping_email'] = $order->email;

            $return[$order->id]['items'] = $order->items->map(function ($item) {

                $rate =  $item->tax / $item->price * 100;

                return collect([
                    'id' => $item->buyable->id,
                    'reference' => $item->key,
                    'sku' => $item->buyable->sku,
                    'barcode' => $item->buyable->barcode,
                    'name' => $item->buyable->product->name,
                    'quantity' => $item->quantity,
                    'price_ex' => ($item->price / 100),
                    'vat' => ($item->tax / 100),
                    'vat_rate' => $rate,
                    'additional_options' => in_array("Gift Wrap", (Arr::pluck(isset($item->options) ? $item->options : [], 'name'))) ? 'Gift Wrap':'',

                ])->toArray();
            })->values();

        }

        return response()->json(($return), JSON_UNESCAPED_UNICODE);
    }

}
