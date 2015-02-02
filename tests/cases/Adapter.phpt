<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class AdapterTest extends Tester\TestCase
{

    /** @var \UniMapper\Flexibee\Adapter $adapter */
    private $adapter;

    public function setUp()
    {
        $this->adapter = new UniMapper\Flexibee\Adapter(["host" => "http://localhost:8000", "company" => "testCompany"]);
    }

    public function testCreateDelete()
    {
        $query = $this->adapter->createDelete("objednavka-prijata");
        Assert::same("objednavka-prijata.json?code-in-response=true", $query->getRaw());
    }

}

$testCase = new AdapterTest;
$testCase->run();