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
        Assert::same("", TestEntity::unmapStitky(null));
        Assert::same("", TestEntity::unmapStitky([]));
        Assert::same("", TestEntity::unmapStitky(""));
    }

    public function testMapStitky()
    {
        Assert::same(["val1", "val2"], TestEntity::mapStitky(" val1 , val2 "));
        Assert::same(["val1", ""], TestEntity::mapStitky("val1, "));
        Assert::same([], TestEntity::mapStitky(null));
        Assert::same([], TestEntity::mapStitky(""));
    }

}

$testCase = new EntityTest;
$testCase->run();