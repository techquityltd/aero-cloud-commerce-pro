<?php

namespace Techquity\CloudCommercePro\Http\Controllers;

use Aero\Cart\Models\Order;
use Aero\Cart\Models\OrderStatus;
use Aero\Catalog\Events\ProductUpdated;
use Aero\Catalog\Models\Category;
use Aero\Catalog\Models\Variant;
use Aero\Catalog\Models\Product;
use Illuminate\Support\Arr;

use Illuminate\Http\Request;

class CcpController
{
    public function csv() {

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=csv_export.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $variants = Variant::whereHas('product', function ($query) {
            $query->active();
        })->select('id', 'sku', 'product_id', 'stock_level')
            ->addSelect(['name' => Product::select('name')->whereColumn('products.id', 'variants.product_id')])
            ->cursor();

        $columns = array('parent_id', 'parent_ref', 'variant_id', 'sku' ,'barcode', 'manufacturer', 'name', 'summary', 'description', 'image', 'categories', 'tags', 'attributes', 'url', 'price', 'price_ex', 'vat_amount', 'vat_rate', 'stock');

        $callback = function() use ($variants, $columns) {

            $file = fopen('php://output', 'w');

            fputcsv($file, $columns);

            foreach($variants as $variant) {

                // Pricing
                $price_inc = ($variant->getPriceForQuantity(1)->sale_value_inc / 100);
                $price_ex = ($variant->getPriceForQuantity(1)->sale_value_ex / 100);
                $vat_amount = (($variant->getPriceForQuantity(1)->sale_value_inc-$variant->getPriceForQuantity(1)->sale_value_ex) / 100);
                $vat_rate = ($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / $variant->getPriceForQuantity(1)->sale_value_ex * 100;

                // Image
                $image = isset($variant->product->images->first()->file) ? trim(env('APP_URL').'/').($variant->product->images->first()->file) : null;


                // Categories
                $categories = $variant->product->categories->map(function($category) {
                    return collect($category->getAncestors()->pluck('name'))->push($category->name)->implode(' > ');
                });
                $categories = (implode($categories->toArray(), "|"));

                // Tags
                $tags = $variant->product->tags->map(function($tag) {
                    return ($tag->group->name.':'.$tag->name);
                });
                $tags = (implode($tags->toArray(), "|"));

                // Attributes
                $attributes = $variant->attributes->map(function($attribute) {
                    return ($attribute->group->name.':'.$attribute->name);
                });
                $attributes = (implode($attributes->toArray(), "|"));


                fputcsv($file, array($variant->product_id, $variant->product->model, $variant->id, $variant->sku, $variant->barcode, $variant->product->manufacturer->name, $variant->product->name, $variant->product->summary, $variant->product->description, $image, $categories, $tags, $attributes, $variant->product->getUrl(true), $price_inc, $price_ex, $vat_amount, $vat_rate, $variant->stock_level));

            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

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

    /**
     * Update order status from CCP.
     *
     *
     * @return void
     */
    public function dispatch(Request $request) : void {

        if ($request->isMethod('post')) {

            $json = json_decode($request->getContent(), true);

            if(is_array($json)) {

                foreach($json as $order) {

                    if(isset($order['reference']) && isset($order['status']) && $orderRecord = Order::where('reference', '=', $order['reference'])->first()) {

                        switch($order['status']) {

                            case "shipped":
                                $orderRecord->setOrderStatus(OrderStatus::forState(OrderStatus::DISPATCHED)->first());
                                break;

                        }
                    }
                }
            }
        }
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

                $return[] = (['id' => $descendant->id, 'name' => $descendant->name, 'parent_id' => $descendant->parent_id]);

            }

        }

        return response()->json($return, JSON_UNESCAPED_UNICODE);

    }

    /**
     * Listings for CCP.
     *
     *
     * @return Object
     */
    public function listings($sku=null)
    {
        $return = [];

        foreach (Product::when($sku, function($query) use($sku){$query->whereHas('variants', function($query) use ($sku){$query->where('sku', $sku);});})->cursor() as $product) {

            $return[] = ([

                'id' => $product->id,
                'parent_ref' => $product->model,
                'url' => $product->getUrl(true),
                'name' => $product->name,
                'summary' => $product->summary,
                'description' => $product->description,

                'images' => $product->images->where('default', 1)->map(function($image){
                    return collect(['url' => trim(env('APP_URL').'/').($image->file)])->toArray();
                })->values(),

                'categories' => $product->categories->map(function($category) {

                    return collect([
                        'id' => $category->id,
                        'name' => htmlspecialchars(
                            collect($category->getAncestors()->pluck('name'))->push($category->name)->implode(' > '),
                            ENT_QUOTES | ENT_HTML5
                        ),

                    ])->toArray();
                })->values(),

                'manufacturer' => $product->manufacturer->name,

                'tags' => $product->tags->map(function($tag) {

                    return collect([
                        'name' => $tag->group->name,
                        'value' => $tag->name,

                    ])->toArray();
                })->values(),


                'variants' => $product->variants->map(function($variant){

                    $inc = setting('prices_inserted_inc_tax');

                    $rate = ($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / $variant->getPriceForQuantity(1)->sale_value_ex * 100;

                    return collect([
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'images' => $variant->images->map(function($image) {
                            return collect(['url' => trim(env('APP_URL').'/').($image->file)])->toArray();
                        })->values(),

                        'stock' => $variant->stock_level,
                        'vat_rate' => round($rate),
                        'price' => ($variant->getPriceForQuantity(1)->sale_value_inc / 100),
                        'price_ex' => ($variant->getPriceForQuantity(1)->sale_value_ex / 100),
                        'vat_amount' => (($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / 100),

                        'attributes' => $variant->attributes->map(function($attribute) {

                            return collect([
                                'name' => $attribute->group->name,
                                'value' => $attribute->name

                            ])->toArray();
                        })->values(),

                    ])->toArray();


                })->values(),

            ]);



        };

        return response()->json(($return), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Listings for CCP.
     *
     *
     * @return Object
     */
    public function variants()
    {
        $return = [];

        foreach (Variant::cursor() as $variant) {

            if(isset($variant->product)) {

                $return[$variant->id]['id'] = $variant->id;
                $return[$variant->id]['sku'] = $variant->sku;
                $return[$variant->id]['barcode'] = $variant->barcode;
                $return[$variant->id]['image'] = isset($variant->product->images->first()->file) ? trim(env('APP_URL').'/').($variant->product->images->first()->file) : null;
                $return[$variant->id]['url'] = $variant->product->getUrl(true);
                $return[$variant->id]['name'] = $variant->product->name;
                $return[$variant->id]['manufacturer'] = $variant->product->manufacturer->name;
                $return[$variant->id]['parent_ref'] = $variant->product->model;

                $return[$variant->id]['categories'] = $variant->product->categories->map(function($category) {

                    return collect([
                        'id' => $category->id,
                        'name' => htmlspecialchars(
                            collect($category->getAncestors()->pluck('name'))->push($category->name)->implode(' > '),
                            ENT_QUOTES | ENT_HTML5
                        ),

                    ])->toArray();
                })->values();

                $return[$variant->id]['tags'] = $variant->product->tags->map(function($tag) {

                    return collect([
                        'name' => $tag->group->name,
                        'value' => $tag->name,

                    ])->toArray();
                })->values();

                $inc = setting('prices_inserted_inc_tax');

                if($inc) {

                    $return[$variant->id]['price'] = ($variant->getPriceForQuantity(1)->sale_value_inc / 100);

                } else {

                    $return[$variant->id]['price_ex'] = ($variant->getPriceForQuantity(1)->sale_value_ex / 100);
                    $return[$variant->id]['vat_amount'] = (($variant->getPriceForQuantity(1)->sale_value_inc-$variant->getPriceForQuantity(1)->sale_value_ex) / 100);
                }

                $rate = ($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / $variant->getPriceForQuantity(1)->sale_value_ex * 100;

                $return[$variant->id]['vat_rate'] = round($rate);
                $return[$variant->id]['stock'] = $variant->stock_level;

                $return[$variant->id]['attributes'] = $variant->attributes->map(function($attribute){
                    return ['name' => $attribute->group->name, 'value' => $attribute->name];
                })->values();

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
    public function orders($orderReference=null)
    {
        $return = [];

        foreach (Order::whereIn('order_status_id', config('aero.cloudcommercepro.order_statuses'))->when($orderReference, function($query) use($orderReference){$query->where('reference', '=', $orderReference);})->cursor() as $order) {


            $return[] = ([

                'id' => $order->id,
                'reference' => $order->reference,
                'order_status' => $order->status->name,
                'order_date' => $order->created_at,
                'payments' => $order->payments->map(function($payment) use ($order) {

                    return collect([
                        'status' => $payment->state,
                        'method' => $payment->method->name,
                        'date' => $payment->created_at,

                    ])->toArray();
                })->values(),

                'total' => ($order->Total + $order->TotalTax) / 100,
                'shipping' => ($order->ShippingRounded) / 100,
                'discount' => ($order->DiscountRounded) / 100,
                'currency' => $order->currency->code,
                'shipping_method' => isset($order->shippingMethod->name) ? $order->shippingMethod->name : null,
                'shipping_date' => null,

                'billing_name' => $order->billingAddress->first_name ." ".$order->billingAddress->last_name,
                'billing_address_company' => $order->billingAddress->company,
                'billing_address1' => $order->billingAddress->line_1,
                'billing_address2' => $order->billingAddress->line_2,
                'billing_city' => $order->billingAddress->city,
                'billing_state' =>  $order->billingAddress->zone_name,
                'billing_zip' => $order->billingAddress->postcode,
                'billing_country' => $order->billingAddress->country_code,
                'billing_phone' => $order->billingAddress->phone,
                'billing_mobile' => $order->billingAddress->mobile,
                'billing_email' => $order->email,

                'shipping_name' => $order->shippingAddress->first_name ." ".$order->billingAddress->last_name,
                'shipping_address_company' => $order->shippingAddress->company,
                'shipping_address1' => $order->shippingAddress->line_1,
                'shipping_address2' => $order->shippingAddress->line_2,
                'shipping_city' => $order->shippingAddress->city,
                'shipping_state' => $order->shippingAddress->zone_name,
                'shipping_zip' => $order->shippingAddress->postcode,
                'shipping_country' => $order->shippingAddress->country_code,
                'shipping_phone' => $order->shippingAddress->phone,
                'shipping_mobile' => $order->shippingAddress->mobile,
                'shipping_email' => $order->email,

                'items' => $order->items->map(function ($item) {

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
                })->values()

            ]);





        }

        return response()->json(($return), JSON_UNESCAPED_UNICODE);
    }

}
