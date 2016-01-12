<?php

use Tester\Assert;
use UniMapper\Entity\Filter;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class QueryTest extends Tester\TestCase
{

    private $query;

    public function setUp()
    {
        $this->query = new \UniMapper\Flexibee\Query("objednavka-prijata");
    }

    public function testSetFilterIn()
    {
        $this->query->setFilter(["in" => [Filter::EQUAL => [1, 2]]]);
        Assert::same(
            "objednavka-prijata/(in IN (1,2)).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterEqual()
    {
        $this->query->setFilter(["equal" => [Filter::EQUAL=> 1]]);
        Assert::same(
            "objednavka-prijata/(equal = 1).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterEqualString()
    {
        $this->query->setFilter(["equal" => [Filter::EQUAL=> "1"]]);
        Assert::same(
            "objednavka-prijata/(equal = '1').json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterNotEqual()
    {
        $this->query->setFilter(["notEqual" => [Filter::NOT => 1]]);
        Assert::same(
            "objednavka-prijata/(notEqual != 1).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterGreater()
    {
        $this->query->setFilter(["greater" => [Filter::GREATER => 1]]);
        Assert::same(
            "objednavka-prijata/(greater > 1).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterGreaterString()
    {
        $this->query->setFilter(["greater" => [Filter::GREATER => "1"]]);
        Assert::same(
            "objednavka-prijata/(greater > '1').json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterLess()
    {
        $this->query->setFilter(["less" => [Filter::LESS => 1]]);
        Assert::same(
            "objednavka-prijata/(less < 1).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterLessEqual()
    {
        $this->query->setFilter(["lessEqual" => [Filter::LESSEQUAL => 1]]);
        Assert::same(
            "objednavka-prijata/(lessEqual <= 1).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterGreaterEqual()
    {
        $this->query->setFilter(["greaterEqual" => [Filter::GREATEREQUAL => 1]]);
        Assert::same(
            "objednavka-prijata/(greaterEqual >= 1).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsTrue()
    {
        $this->query->setFilter(["isTrue" => [Filter::EQUAL => true]]);
        Assert::same(
            "objednavka-prijata/(isTrue IS true).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNotTrue()
    {
        $this->query->setFilter(["isNotTrue" => [Filter::NOT => true]]);
        Assert::same(
            "objednavka-prijata/(isNotTrue IS false).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsFalse()
    {
        $this->query->setFilter(["isFalse" => [Filter::EQUAL => false]]);
        Assert::same(
            "objednavka-prijata/(isFalse IS false).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNotFalse()
    {
        $this->query->setFilter(["isNotFalse" => [Filter::NOT => false]]);
        Assert::same(
            "objednavka-prijata/(isNotFalse IS true).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNull()
    {
        $this->query->setFilter(["isNull" => [Filter::EQUAL => null]]);
        Assert::same(
            "objednavka-prijata/(isNull IS NULL).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNotNull()
    {
        $this->query->setFilter(["isNotNull" => [Filter::NOT => null]]);
        Assert::same(
            "objednavka-prijata/(isNotNull IS NOT NULL).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNullString()
    {
        $this->query->setFilter(["isNullString" => [Filter::EQUAL => ""]]);
        Assert::same(
            "objednavka-prijata/(isNullString IS NULL).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsEmptyAsQuotationMarks()
    {
        $this->query->setFilter(["emptyAsQuotationMarks" => [Filter::EQUAL => '""']]);
        Assert::same(
            "objednavka-prijata/(emptyAsQuotationMarks IS empty).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsEmptyAsInvertedCommas()
    {
        $this->query->setFilter(["emptyAsInvertedCommas" => [Filter::EQUAL => "''"]]);
        Assert::same(
            "objednavka-prijata/(emptyAsInvertedCommas IS empty).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNotEmptyInvertedCommas()
    {
        $this->query->setFilter(["emptyAsInvertedCommas" => [Filter::NOT => "''"]]);
        Assert::same(
            "objednavka-prijata/(emptyAsInvertedCommas IS NOT empty).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterIsNotEmptyQuotationMarks()
    {
        $this->query->setFilter(["emptyQuotationMarks" => [Filter::NOT => "''"]]);
        Assert::same(
            "objednavka-prijata/(emptyQuotationMarks IS NOT empty).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterNotIn()
    {
        $this->query->setFilter(["notIn" => [Filter::NOT => [1, 2]]]);
        Assert::same(
            "objednavka-prijata/((notIn != 1 AND notIn != 2)).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterNotInStrings()
    {
        $this->query->setFilter(["notIn" => [Filter::NOT => ["1", "2"]]]);
        Assert::same(
            "objednavka-prijata/((notIn != '1' AND notIn != '2')).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterStitky()
    {
        $this->query->setFilter(["stitky" => [Filter::EQUAL => "stitek1,stitek2"]]);
        Assert::same(
            "objednavka-prijata/((stitky = 'stitek1' OR stitky = 'stitek2')).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterContain()
    {
        \UniMapper\Flexibee\Adapter::$likeWithSimilar = false;
        $this->query->setFilter(["like" => [Filter::CONTAIN => "foo%"]]);
        Assert::same(
            "objednavka-prijata/(like LIKE 'foo%').json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterContainWithLikeSimilar()
    {
        $this->query->setFilter(["likeSimilar" => [Filter::CONTAIN => "%foo%"]]);
        Assert::same(
            "objednavka-prijata/(likeSimilar LIKE SIMILAR '%foo%').json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterEnds()
    {
        \UniMapper\Flexibee\Adapter::$likeWithSimilar = false;
        $this->query->setFilter(["ends" => [Filter::END => "%foo"]]);
        Assert::same(
            "objednavka-prijata/(ends ENDS '%foo').json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterBegins()
    {
        \UniMapper\Flexibee\Adapter::$likeWithSimilar = false;
        $this->query->setFilter(["begins" => [Filter::START => "foo%"]]);
        Assert::same(
            "objednavka-prijata/(begins BEGINS 'foo%').json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterMultipleModifiers()
    {
        \UniMapper\Flexibee\Adapter::$likeWithSimilar = false;
        $this->query->setFilter(
            [
                "id" => [
                    Filter::GREATER => 1,
                    Filter::LESS => 2
                ]
            ]
        );
        Assert::same(
            "objednavka-prijata/(id > 1 AND id < 2).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterGroups()
    {
        $this->query->setFilter(
            [
                [
                    "one" => [Filter::EQUAL => 1],
                    "two" => [Filter::EQUAL => 2]
                ],
                [
                    "three" => [Filter::EQUAL => 3],
                    "four" => [Filter::EQUAL => 4]
                ]
            ]
        );
        Assert::same(
            "objednavka-prijata/(((one = 1 AND two = 2) AND (three = 3 AND four = 4))).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

    public function testSetFilterGroupsWithOr()
    {
        $this->query->setFilter(
            [
                [
                    "one" => [Filter::EQUAL => 1],
                    "two" => [Filter::EQUAL => 2]
                ],
                [
                    Filter::_OR => [
                        "three" => [Filter::EQUAL => 3],
                        "four" => [Filter::EQUAL => 4]
                    ]
                ]
            ]
        );
        Assert::same(
            "objednavka-prijata/(((one = 1 AND two = 2) AND (((three = 3 OR four = 4))))).json?code-as-id=true",
            rawurldecode($this->query->getRaw())
        );
    }

}

$testCase = new QueryTest;
$testCase->run();