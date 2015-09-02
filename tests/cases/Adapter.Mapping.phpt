<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @property Date     $date
 * @property DateTime $time
 */
class Entity extends UniMapper\Entity
{}

/**
 * @testCase
 */
class AdapterMappingTest extends Tester\TestCase
{

    /** @var \UniMapper\Flexibee\Adapter\Mapping $mapping */
    private $mapping;

    public function setUp()
    {
        $this->mapping = new \UniMapper\Flexibee\Adapter\Mapping;
    }

    public function testUnmapValue()
    {
        Assert::same(
            "1999-12-30",
            $this->mapping->unmapValue(
                UniMapper\Entity\Reflection::load("Entity")->getProperty("date"),
                new DateTime("1999-12-30 23:59:59.00")
            )
        );
        Assert::same(
            "1999-12-30T23:59:59+01:00",
            $this->mapping->unmapValue(
                UniMapper\Entity\Reflection::load("Entity")->getProperty("time"),
                new DateTime("1999-12-30 23:59:59.00")
            )
        );
    }

}

$testCase = new AdapterMappingTest;
$testCase->run();