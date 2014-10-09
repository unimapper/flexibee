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

    public function testCreateDelete()
    {
        $query = $this->adapter->createDelete("objednavka-prijata");
        Assert::same("objednavka-prijata.json?code-in-response=true", $query->getRaw());
    }

}

$testCase = new AdapterTest;
$testCase->run();