<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class TestEntity extends UniMapper\Flexibee\Entity
{}

class EntityTest extends Tester\TestCase
{

    public function testUnmapStitky()
    {
        Assert::same("val1,val2", TestEntity::unmapStitky(["val1", "val2"]));
    }

    public static function testmapStitky()
    {
        Assert::same(["val1", "val2"], TestEntity::mapStitky(["val1, val2 "]));
    }

}

$testCase = new EntityTest;
$testCase->run();