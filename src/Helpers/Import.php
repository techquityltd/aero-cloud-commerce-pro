<?php namespace Techquity\CloudCommercePro\Helpers;


use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Import
{
    /**
     * @param $column
     * @param $row
     * @param $data
     */
    public static function addImages($images, $product, &$data, $defaults): void
    {
        foreach ($images as $key => $image) {
            $data[] = array_merge($defaults, [
                'Model' => $product['parent_ref'],
                'SKU' => $product['sku'] ?? null,
                'Image Src' => $image['url'],
                'Image Position' => $key,
                'Image Is Default' => 1,
            ]);
        }
    }

    /**
     * @param  string  $column
     * @param $row
     * @param $data
     */
    public static function addCategories($categories, $product, &$data, $defaults, $parentRef=null): void
    {
        foreach ($categories as $category) {

            $categoryElements = explode(' &gt; ', $category['name']);

            $categoryString = collect($categoryElements)->reduce(static function ($parent, $child) {

                if ($parent === null) {
                    return $child;
                }

                if ($parent !== null && Str::startsWith($child, $parent)) {
                    $child = trim(Str::replaceFirst($parent, '', $child));
                }

                return "{$parent}\n{$child}";

            });

            $data[] = array_merge($defaults, [
                'Model' => $product['parent_ref'] ?? $parentRef,
                'SKU' => $product['sku'] ?? '',
                'Category' => $categoryString,
            ]);
        }
    }


    /**
     * @param $name
     * @param $column
     * @param $row
     * @param $data
     * @param  array  $images
     * @param  bool  $listable
     */
    public static function addAttribute($name, $value, $product, &$data, $defaults, $parentRef=null): void
    {
        if (! empty($value)) {
            $data[] = array_merge($defaults, [
                'Model' => $product['parent_ref'] ?? $parentRef,
                'SKU' => $product['sku'],
                'Stock Level' => $product['stock'],
                'Attribute Group' => $name,
                'Attribute Name' => $value,
            ]);

        }
    }
    /**
     * @param $name
     * @param $column
     * @param $row
     * @param $data
     */
    public static function addTags($name, $value, $product, &$data, $defaults): void
    {
        $data[] = array_merge($defaults, [
            'Model' => $product['parent_ref'],
            'SKU' => $product['sku'] ?? null,
            'Tag Group' => $name,
            'Tag Name' => $value,
        ]);
    }
    /**
     * @param $row
     * @return mixed
     */
    private function getBarcode($row)
    {
        $code = empty($row['EAN'])
            ? $row['UPC']
            : $row['EAN'];
        return empty($code)
            ? null
            : (int) $code;
    }
    private function addAdditionalFields($row, &$data): void
    {
        foreach ($this->additionalFields as $field => $column) {
            $this->addAdditionalField($field, $row[$column], $data);
        }
    }
    private function addAdditionalField($field, $column, &$data, $defaults)
    {
        $data[] = array_merge($defaults, [
            "additional:{$field}" => $column,
        ]);
    }

}
