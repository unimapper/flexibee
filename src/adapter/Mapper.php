<?php

namespace UniMapper\Flexibee\Adapter;

use UniMapper\Reflection,
    UniMapper\Association;

class Mapper extends \UniMapper\Adapter\Mapper
{

    const DATETIME_FORMAT = "Y-m-d\TH:i:sP";

    public function mapValue(Reflection\Entity\Property $property, $value)
    {
        if ($property->isAssociation()
            && $property->getAssociation() instanceof Association\ManyToOne
            && !empty($value)
        ) {
            $value = $value[0];
        }

        return parent::mapValue($property, $value);
    }

    public function mapEntity(Reflection\Entity $entityReflection, $data)
    {
        if (isset($data->{"external-ids"}) && isset($data->id)) {
            // Replace id value with 'code:...' from external-ids automatically

            foreach ($data->{"external-ids"} as $externalId) {

                if (substr($externalId, 0, 5) === "code:") {

                    $data->id = $externalId;
                    break;
                }
            }
        }

        return parent::mapEntity($entityReflection, $data);
    }

    public function unmapValue(Reflection\Entity\Property $property, $value)
    {
        $value = parent::unmapValue($property, $value);

        if ($value === null) {
            $value = "";
        } elseif ($value instanceof \DateTime) {

            $value = $value->format(self::DATETIME_FORMAT);
            if ($value === false) {
                throw new \Exception("Can not convert DateTime automatically!");
            }
        }

        return $value;
    }

}