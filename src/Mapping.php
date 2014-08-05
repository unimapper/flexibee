<?php

namespace UniMapper\Flexibee;

use UniMapper\Reflection;

class Mapping extends \UniMapper\Mapping
{

    public function unmapValue(Reflection\Entity\Property $property, $value)
    {
        $value = parent::unmapValue($property, $value);
        if ($value === null) {
            $value = "";
        } elseif ($value instanceof \DateTime) {
            $value = $value->format(Adapter::DATETIME_FORMAT);
        }
        return $value;
    }

}
