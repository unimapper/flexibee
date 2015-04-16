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
                        ["three", "=", 3, "AND"],
                        ["foo", "!=", "foo2", "AND"],
                        ["greaterThan", ">", 4, "AND"],
                        ["lessThan", "<", 5, "AND"],
                        ["lessThanEqual", "<=", 6, "AND"],
                        ["moreLess", "<>", 7, "AND"],
                        ["moreThanEqual", ">=", 8, "AND"],
                        ["isTrue", "IS", true, "AND"],
                        ["isNotTrue", "IS NOT", true, "AND"],
                        ["isFalse", "IS", false, "AND"],
                        ["null", "IS", null, "AND"],
                        ["nullString", "IS", "", "AND"],
                        ["emptyAsQuotationMarks", "IS", '""', "AND"],
                        ["emptyAsInvertedCommas", "IS", "''", "AND"],
                        ["id", "NOT IN", [9, 10], "OR"],
                        ["stitky", "IN", "stitek1,stitek2", "OR"],
                        [
                            [
                                ["similar", "LIKE", "%foo%", "AND"],
                                ["begins", "LIKE", "foo%", "AND"],
                                ["ends", "LIKE", "%foo", "AND"]
                            ],
                            "OR"
                        ]
                    ],
                    "AND"
                ]
            ]
        );
        Assert::same(
            "objednavka-prijata/(%28id%20IN%20%28%271%27%2C%272%27%29%20AND%20three%20%3D%20%273%27%20AND%20foo%20%21%3D%20%27foo2%27%20AND%20greaterThan%20%3E%20%274%27%20AND%20lessThan%20%3C%20%275%27%20AND%20lessThanEqual%20%3C%3D%20%276%27%20AND%20moreLess%20%3C%3E%20%277%27%20AND%20moreThanEqual%20%3E%3D%20%278%27%20AND%20isTrue%20IS%20true%20AND%20isNotTrue%20IS%20false%20AND%20isFalse%20IS%20false%20AND%20null%20IS%20NULL%20AND%20nullString%20IS%20NULL%20AND%20emptyAsQuotationMarks%20IS%20empty%20AND%20emptyAsInvertedCommas%20IS%20empty%20OR%20%28id%20%21%3D%20%279%27%20AND%20id%20%21%3D%20%2710%27%29%20OR%20%28stitky%20%3D%20%27stitek1%27%20OR%20stitky%20%3D%20%27stitek2%27%29%20OR%20%28similar%20LIKE%20SIMILAR%20%27foo%27%20AND%20begins%20BEGINS%20SIMILAR%20%27foo%27%20AND%20ends%20ENDS%20SIMILAR%20%27foo%27%29%29).json?code-as-id=true",
            $query->getRaw()
        );
    }

}

$testCase = new QueryTest;
$testCase->run();