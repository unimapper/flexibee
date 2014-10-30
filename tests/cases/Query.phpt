<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class QueryTest extends Tester\TestCase
{

    public function testConditions()
    {
        $query = new \UniMapper\Flexibee\Query("objednavka-prijata");
        $query->setConditions(
            [
                [
                    [
                        ["id", "IN", [1, 2], "AND"],
                        ["isTrue", "IS", true, "AND"],
                        ["isFalse", "IS", false, "AND"],
                        ["id", "NOT IN", [3, 4], "OR"],
                        [
                            [
                                ["similar", "COMPARE", "%foo%", "AND"],
                                ["begins", "COMPARE", "foo%", "AND"],
                                ["ends", "COMPARE", "%foo", "AND"]
                            ],
                            "OR"
                        ]
                    ],
                    "AND"
                ]
            ]
        );
        Assert::same(
            "objednavka-prijata/(%28id%20IN%20%28%271%27%2C%272%27%29%20AND%20isTrue%20IS%20true%20AND%20isFalse%20IS%20false%20OR%20%28id%20%21%3D%20%273%27%20AND%20id%20%21%3D%20%274%27%29%20OR%20%28similar%20LIKE%20SIMILAR%20%27foo%27%20AND%20begins%20BEGINS%20%27foo%27%20AND%20ends%20ENDS%20%27foo%27%29%29).json?code-as-id=true",
            $query->getRaw()
        );
    }

}

$testCase = new QueryTest;
$testCase->run();