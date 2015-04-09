<?php

namespace UniMapper\Flexibee;

abstract class Entity extends \UniMapper\Entity
{

    /**
     * Converts 'stitky' to an array
     *
     * @param string $value
     *
     * @return array
     */
    public static function mapStitky($value)
    {
        return $value ? array_map("trim", explode(",", $value)) : [];
    }

    /**
     * Converts 'stitky' back to string
     *
     * @param array $value
     *
     * @return string
     */
    public static function unmapStitky($value)
    {
        if (is_array($value)) {
            return $value ? implode(",", array_map("trim", $value)) : "";
        }
        return "";
    }

}