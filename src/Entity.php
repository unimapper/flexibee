<?php

namespace UniMapper\Flexibee;

abstract class Entity extends \UniMapper\Entity
{

    /**
     * Converts 'stitky' to an array
     *
     * @param string $string
     *
     * @return array
     */
    public static function mapStitky($string)
    {
        return $string ? array_map('trim', explode(',', $string)) : [];
    }

    /**
     * Converts 'stitky' back to string
     *
     * @param array $array
     *
     * @return string
     */
    public static function unmapStitky(array $array)
    {
        return $array ? implode(',', array_map('trim',$array)) : "";
    }

}