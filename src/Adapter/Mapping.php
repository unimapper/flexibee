<?php

namespace UniMapper\Flexibee\Adapter;

use UniMapper\Association;
use UniMapper\Entity\Reflection;

class Mapping extends \UniMapper\Adapter\Mapping
{

    /** @var array */
    public static $format = [
        Reflection\Property::TYPE_DATE => "Y-m-d",
        Reflection\Property::TYPE_DATETIME => "Y-m-d\TH:i:sP"
    ];

    public function mapValue(Reflection\Property $property, $value)
    {
        if ($property->hasOption(Reflection\Property::OPTION_ASSOC)
            && $property->getOption(Reflection\Property::OPTION_ASSOC) instanceof Association\ManyToOne
            && !empty($value)
        ) {
            return $value[0];
        }

        return $value;
    }

    public function unmapValue(Reflection\Property $property, $value)
    {
        if ($value === null) {
            return "";
        } elseif ($value instanceof \DateTime
            && isset(self::$format[$property->getType()])
        ) {

            $value = $value->format(self::$format[$property->getType()]);
            if ($value === false) {
                throw new \Exception("Can not convert DateTime automatically!");
            }
        }

        return $value;
    }

}