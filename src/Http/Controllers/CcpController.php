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
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use Techquity\CloudCommercePro\Helpers\Import;
use Illuminate\Support\Facades\Artisan;

class CcpController
{
    protected $defaults = [];
    protected $images = [];

    public function __construct() {

        $this->defaults = [
            'Model' => '',
            'Name' => '',
            'Manufacturer' => '',
            'Category' => '',
            'Summary' => '',
            'Visible' => '',
            'Description' => '',
            'Image Src' => '',
            'Image Alt Text' => '',
            'Image Position' => '',
            'Image Is Default' => '',
            'SKU' => '',
            'Barcode' => '',
            'Weight' => '',
            'Weight Unit' => '',
            'Stock Level' => '',
            'Infinite Stock' => '',
            'Currency' => '',
            'Tax Group' => '',
            'Variant Visible' => '',
            'Price Quantity' => '',
            'Price' => '',
            'Cost Price' => '',
            'Sale Price' => '',
            'Retail Price' => '',
            'Heading' => '',
            'Page Title' => '',
            'Meta Description' => '',
            'Tag Group' => '',
            'Tag Name' => '',
            'Attribute Group' => '',
            'Attribute Name' => '',
            'Attribute Group Is Listable' => '',
            'Upsell' => '',
            'Published At' => '',
            'Created At' => '',
        ];

    }


    public function csv() {

        ini_set('memory_limit', '2G');
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
                    } else {
                        Log::info('CCP unable to update stock: ' . json_encode($stock));
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
        $sku = urldecode($sku);

        foreach (Product::when($sku, function($query) use($sku){$query->whereHas('variants', function($query) use ($sku){$query->where('sku', $sku);});})->jsonPaginate() as $product) {

            $return[] = ([

                'id' => $product->id,
                'parent_ref' => $product->model,
                'url' => $product->getUrl(true),
                'name' => $product->name,
                'summary' => $product->summary,
                'description' => $product->description,

                'images' => $product->images->where('default', 1)->map(function($image){
                    return collect(['url' => route('image-resize', ['500x500', $image->file])])->toArray();

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

                'manufacturer' => optional($product->manufacturer)->name,

                'tags' => $product->tags->map(function($tag) {

                    return collect([
                        'name' => $tag->group->name,
                        'value' => $tag->name,

                    ])->toArray();
                })->values(),


                'variants' => $product->variants->map(function($variant){

                    $inc = setting('prices_inserted_inc_tax');

                    if($variant->getPriceForQuantity(1)->sale_value_ex > 0) {
                        $rate = ($variant->getPriceForQuantity(1)->sale_value_inc - $variant->getPriceForQuantity(1)->sale_value_ex) / $variant->getPriceForQuantity(1)->sale_value_ex * 100;
                    } else {
                        $rate = 20;
                    }

                    return collect([
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'barcode' => $variant->barcode,
                        'images' => $variant->images->map(function($image) {
                            return collect(['url' => route('image-resize', ['500x500', $image->file])])->toArray();
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
     * Create / Edit / Remove Product via for CCP.
     *
     *
     * @return Object
     */
    public function product(Request $request) {

        if ($request->isMethod('post')) {

            $product = json_decode($request->getContent(), true);

            $data = [];

            if(isset($product['parent_ref']) && isset($product['name'])) {

                $data[] = array_merge($this->defaults, [
                    'Model' => $product['parent_ref'],
                    'Name' => $product['name'],
                    'Visible' => $product['visible'] ?? 1,
                    'Manufacturer' => isset($product['manufacturer']) ? $product['manufacturer']:null,
                    'Summary' => isset($product['summary']) ? $product['summary']:null,
                    'Description' => isset($product['description']) ? $product['description']:null,
                    'Heading' => isset($product['page_heading']) ? $product['page_heading']:null,
                    'Page Title' => isset($product['page_title']) ? $product['page_title']:null,
                    'Meta Description' => isset($product['meta_description']) ? $product['meta_description']:null,
                    'Published At' => isset($product['published_at']) ? $product['published_at']:null,
                    'Created At' => isset($product['created_at']) ? $product['created_at']:null,
                ]);

                if(isset($product['images']) && count($product['images']) > 0) {
                    Import::addImages($product['images'], $product, $data, $this->defaults);
                }

                if(isset($product['categories']) && count($product['categories']) > 0) {
                    Import::addCategories($product['categories'], $product, $data, $this->defaults);
                }


                if(isset($product['tags']) && count($product['tags']) > 0) {
                    foreach($product['tags'] as $tag) {
                        Import::addTags($tag['name'], $tag['value'], $product, $data, $this->defaults);
                    }
                }


                if(isset($product['variants']) && count($product['variants']) > 0) {

                    foreach($product['variants'] as $variant) {

                        $data[] = array_merge($this->defaults, [
                            'Model' => $product['parent_ref'],
                            'SKU' => $variant['sku'],
                            'Barcode' => $variant['barcode'] ?? null,
                            'Weight' =>  $variant['weight'] ?? 0,
                            //'Weight Unit' => 'g',
                            'Stock Level' => $variant['stock'] ?? null,
                            //'Infinite Stock' => 0,
                            'Currency' => 'GBP',
                            'Cost Price' => $variant['cost_price'] ?? null,
                            'Tax Group' => 'Taxable Product',
                            'Price Quantity' => 1,
                            'Price' => $variant['price'] ?? null,
                            'Sale Price' => null,
                            'Retail Price' => null,
                            'Variant Visible' => $variant['variant_visible'] ?? 1,
                        ]);

                        //Import::addCategories($product['categories'], $variant, $data, $this->defaults, $product['parent_ref']);

                        if(isset($variant['attributes']) && count($variant['attributes']) > 0) {

                            foreach($variant['attributes'] as $attribute) {

                                Import::addAttribute($attribute['name'], $attribute['value'], $variant, $data, $this->defaults, $product['parent_ref']);

                            }

                        }

                    }

                }
                //dd($data);
                $csv = Writer::createFromPath(storage_path("app/cloudcommercepro/queue/products/{$product['parent_ref']}.csv"), 'w+');
                $csv->insertOne(array_keys(Arr::first($data)));
                $csv->insertAll($data);

                return "Successful";
            } else {

                return "Missing 'parent_ref' or 'name'";

            }


        }

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
                $return[$variant->id]['weight'] = $variant->weight;
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
                $return[$variant->id]['cost_price'] = $variant->cost_price;
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
        $orderReference = urldecode($orderReference);

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
                    if ($item->price == 0) {
                        $rate = 0;
                    } else {
                        $rate = $item->tax / $item->price * 100;
                    }

                    if ($item->buyable_id == 0) {
                        return collect([
                            'id' => '0',
                            'reference' => $item->key,
                            'sku' => $item->sku,
                            'barcode' => '',
                            'name' => $item->name,
                            'quantity' => $item->quantity,
                            'price_ex' => ($item->price / 100),
                            'vat' => ($item->tax / 100),
                            'vat_rate' => $rate,
                            'additional_options' => in_array("Gift Wrap", (Arr::pluck(isset($item->options) ? $item->options : [], 'name'))) ? 'Gift Wrap':'',
                        ])->toArray();
                    }

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
