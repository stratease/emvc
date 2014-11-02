<?php
/**
 * Created by PhpStorm.
 * User: kay
 * Date: 11/1/2014
 * Time: 3:03 PM
 */

class ObjectUtil {

    public static function toArray($object)
    {
        $return = [];
        if(is_object($object) || is_array($object)) {
            // if it's iterable, iterate on each field
            foreach($object as $field => $value) {
                // if it's iterable, go deeper
                if(is_array($value)
                    || is_object($value)) {
                    $return[$field] = self::toArray($value);
                } else {
                    $return[$field] = $value;
                }
            }
        }

        return $return;
    }
} 