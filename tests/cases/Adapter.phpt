<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class AdapterTest extends Tester\TestCase
{

    /** @var \Mockery\Mock $connectionMock */
    private $connectionMock;

    /** @var \UniMapper\Flexibee\Adapter $adapter */
    private $adapter;

    public function setUp()
    {
        $this->connectionMock = Mockery::mock("UniMapper\Flexibee\Connection");
        $this->adapter = new UniMapper\Flexibee\Adapter("test", $this->connectionMock);
    }

    public function testDelete()
    {
        $this->connectionMock->shouldReceive("put")
            ->with(
                "entity.json?code-in-response=true",
                ['@update'=>'fail', 'entity' => ['col'=>'val']]
            )
            ->andReturn();

        Assert::null($this->adapter->insert("entity", ["col" => "val"]));
    }

}

$testCase = new AdapterTest;
$testCase->run();