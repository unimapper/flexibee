<?php

namespace UniMapper\Flexibee;

use UniMapper\Reflection;

class Mapping extends \UniMapper\Mapping
{

    const DATETIME_FORMAT = "Y-m-d\TH:i:sP";

    public function unmapValue(Reflection\Entity\Property $property, $value)
    {
        $value = parent::unmapValue($property, $value);
        if ($value === null) {
            $value = "";
        } elseif ($value instanceof \DateTime) {
            $value = $value->format(self::DATETIME_FORMAT);
        }
        return $value;
    }

    public static function unmapOrderBy(Reflection\Entity $entityReflection, array $items)
    {
        $result = [];
        foreach ($items as $name => $direction) {

            if ($direction === "asc") {
                $direction = "A";
            } else {
                $direction = "D";
            }
            $result[] = "order=" . rawurlencode($entityReflection->getProperty($name)->getMappedName()  . "@" . $direction);
        }
        return $result;
    }

    public static function unmapConditions(Reflection\Entity $entityReflection, array $conditions)
    {
        $result = "";

        foreach ($conditions as $condition) {

            if (is_array($condition[0])) {
                // Nested conditions

                list($nestedConditions, $joiner) = $condition;
                $converted = "(" . self::unmapConditions($entityReflection, $nestedConditions) . ")";
                // Add joiner if not first condition
                if ($result !== "") {
                    $result .= " " . $joiner . " ";
                }
                $result .= $converted;

            } else {
                // Simple condition

                list($propertyName, $operator, $value, $joiner) = $condition;

                // Value
                if (is_array($value)) {
                    $value = "('" . implode("','", $value) . "')";
                } elseif ($value instanceof \DateTime) {
                    $value = "'" . $value->format(self::DATETIME_FORMAT) . "'";
                } else {
                    $leftPercent = $rightPercent = false;
                    if (substr($value, 0, 1) === "%") {
                        $value = substr($value, 1);
                        $leftPercent = true;
                    }
                    if (substr($value, -1) === "%") {
                        $value = substr($value, 0, -1);
                        $rightPercent = true;
                    }
                    $value = "'" . $value . "'";
                }

                // Compare
                if ($operator === "COMPARE") {
                    if ($rightPercent && !$leftPercent) {
                        $operator = "BEGINS";
                    } elseif ($leftPercent && !$rightPercent) {
                        $operator = "ENDS";
                    } else {
                        $operator = "LIKE SIMILAR";
                    }
                }

                // IS, IS NOT
                if (($operator === "IS NOT" || $operator === "IS") && $value === "''") {
                    $value = "empty";
                }

                $formatedCondition = $entityReflection->getProperty($propertyName)->getMappedName() . " " . $operator . " " . $value;

                // Check if is it first condition
                if ($result !== "") {
                    $result .= " " . $joiner . " ";
                }

                $result .=  $formatedCondition;
            }
        }

        return $result;
    }

    public static function unmapSelection(Reflection\Entity $entityReflection, array $selection)
    {
        return implode(
            ",",
            self::escapeProperties(
                parent::unmapSelection($entityReflection, $selection)
            )
        );
    }

    /**
     * Escape properties with @ char (polozky@removeAll), @showAs, @ref ...
     *
     * @param array $properties
     *
     * @return array
     */
    public static function escapeProperties(array $properties)
    {
        foreach ($properties as $index => $item) {

            if (self::endsWith($item, "@removeAll")) {
                $properties[$index] = substr($item, 0, -10);
            } elseif (self::endsWith($item, "@showAs") || self::endsWith($item, "@action")) {
                $properties[$index] = substr($item, 0, -7);
            } elseif (self::endsWith($item, "@ref")) {
                $properties[$index] = substr($item, 0, -4);
            }
        }
        return $properties;
    }

    public static function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

}