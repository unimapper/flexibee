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

    public function testCreateInsert()
    {
        $query = $this->adapter->createInsert("objednavka-prijata", [[]]);
        Assert::same("objednavka-prijata.json?code-in-response=true", $query->getRaw());
        Assert::same(array('objednavka-prijata' => array(array('@update' => 'fail'))), $query->data);
    }

    public function testCreateInsertMultiple()
    {
        $query = $this->adapter->createInsert("objednavka-prijata", ["name" => "value"]);
        Assert::same("objednavka-prijata.json?code-in-response=true", $query->getRaw());
        Assert::same(array('objednavka-prijata' => array('name' => 'value', '@update' => 'fail')), $query->data);
    }

    public function testCreateUpdate()
    {
        $query = $this->adapter->createUpdate("objednavka-prijata", [[]]);
        Assert::same("objednavka-prijata.json?code-in-response=true", $query->getRaw());
        Assert::same(array('objednavka-prijata' => array(array('@create' => 'fail'))), $query->data);
    }

    public function testCreateUpdateMultiple()
    {
        $query = $this->adapter->createUpdate("objednavka-prijata", ["name" => "value"]);
        Assert::same("objednavka-prijata.json?code-in-response=true", $query->getRaw());
        Assert::same(array('objednavka-prijata' => array('name' => 'value', '@create' => 'fail')), $query->data);
    }

}

$testCase = new AdapterTest;
$testCase->run();