<?php

namespace Komparu\Utility;

/**
 * Class ArrayHelper
 */
class ArrayHelper
{
    /**
     * Is the array associative?
     * @param array $arr
     * @return bool
     */
    public static function isAssoc(Array $arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Is the array a collection of items?
     * @param array $arr
     * @return bool
     */
    public static function isCollection(Array $arr)
    {
        return !static::isAssoc($arr);
    }

    /**
     * Set or replace an item in an array using dot notation.
     * @return \ArrayAccess
     */
    public static function set(&$collection, $key, $value)
    {
        if (is_null($key)) {
            return $collection = $value;
        }
        // Explode the keys
        $keys = explode('.', $key);

        // Crawl through the keys
        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If we're dealing with an object
            if (is_object($collection)) {
                if (!isset($collection->$key) or !is_array($collection->$key)) {
                    $collection->$key = [];
                }
                $collection = &$collection->$key;

                // If we're dealing with an array
            } else {
                if (!isset($collection[$key]) or !is_array($collection[$key])) {
                    $collection[$key] = [];
                }
                $collection = &$collection[$key];
            }
        }

        // Bind final tree on the collection
        $key = array_shift($keys);
        if (is_array($collection)) {
            $collection[$key] = $value;
        } else {
            $collection->$key = $value;
        }

        return $collection;
    }

    public static function flatten($array, $root = [])
    {
        return array_reduce(
            array_map(function ($value, $key) {
                return ['value' => $value, 'key' => $key];
            }, $array, array_keys($array)),
            function ($carrier, $item) use ($root) {
                $root[] = $item['key'];
                if (is_array($item['value'])) {
                    $carrier = array_merge($carrier, self::flatten($item['value'], $root));
                } else {
                    $carrier[implode('.', $root)] = $item['value'];
                }

                return $carrier;
            }, []);
    }

    public static function getDot($array, $key)
    {
        return array_reduce(explode('.', $key), function ($carrier, $index) {
            return isset($carrier[$index])
                ? $carrier[$index]
                : null;
        }, $array);
    }

    /**
     * Recursively check if an associative array needs to be converted to a collection.
     * This is needed for the Schema package to work properly.
     *
     * @param array $data
     * @return array
     */
    public static function fixCollections(Array $data)
    {
        foreach($data as $property => $val) {

            if(!is_array($val)) continue;
            $first = current($val);

            if(isset($first['name']) && $first['name'] == key($val)) {
                $data[$property] = array_values($data[$property]);
            }
            else {
                $data[$property] = static::fixCollections($data[$property]);
            }

        }

        return $data;
    }


    /**
     * Remove an element by value
     *
     * @param array $array
     * @param $value
     */
    public static function removeByValue(Array &$array, $value) {
        $key = array_search($value,$array);
        if($key!==false){
            unset($array[$key]);
        }
    }
}