<?php

namespace Vanderbilt\REDCap\Tests\Logic;


class XConcatJsTest extends LogicParserTest
{
    private static $calctextPlusSignAllLiteralCases = [
        // only plus sign within calctext string
        "calctext(' + ')" => [
            "literalPlusSignLocations" => [11],
            "expectedOutputString" => "calctext(' + ')"
        ],
        // missing operand within calctext string (i.e. at least one `+` sign not preceded or followed by an operand)
        "calctext(' 1+ ')" => [
            "literalPlusSignLocations" => [12],
            "expectedOutputString" => "calctext(' 1+ ')"
        ],
        "calctext('233 + hhh2 +')" => [
            "literalPlusSignLocations" => [14,21],
            "expectedOutputString" => "calctext('233 + hhh2 +')"
        ],
        "calctext(' aaa+ ')" => [
            "literalPlusSignLocations" => [14],
            "expectedOutputString" => "calctext(' aaa+ ')"
        ],
        "calctext(' + 3 ')" => [
            "literalPlusSignLocations" => [11],
            "expectedOutputString" => "calctext(' + 3 ')"
        ],
        "calctext(' + bbbb ')" => [
            "literalPlusSignLocations" => [11],
            "expectedOutputString" => "calctext(' + bbbb ')"
        ],
        "calctext(' + 4+ ')" => [
            "literalPlusSignLocations" => [11,14],
            "expectedOutputString" => "calctext(' + 4+ ')"
        ],
        // non-alphanumeric character present within calctext string
        "calctext('233) + 2')" => [
            "literalPlusSignLocations" => [15],
            "expectedOutputString" => "calctext('233) + 2')"
        ],
        "calctext('23\)3 + hh\(\)h2')" => [
            "literalPlusSignLocations" => [16],
            "expectedOutputString" => "calctext('23\)3 + hh\(\)h2')"
        ],
        // calctext string contains if statement (ternary operator)
        "calctext(((1)?('1+'):('')))" => [
            "literalPlusSignLocations" => [17],
            "expectedOutputString" => "calctext(((1)?('1+'):('')))"
        ],
        "calctext(((1)?('  +'):('')))" => [
            "literalPlusSignLocations" => [18],
            "expectedOutputString" => "calctext(((1)?('  +'):('')))"
        ],
        "calctext(((1)?('   + 1 '):('')))" => [
            "literalPlusSignLocations" => [19],
            "expectedOutputString" => "calctext(((1)?('   + 1 '):('')))"
        ],
        "calctext(((1)?(\"  +\"):('')))calctext(((1)?(\"  +\"):('')))calctext(\" + \")" => [
            "literalPlusSignLocations" => [18,46,67],
            "expectedOutputString" => "calctext(((1)?(\"  +\"):('')))calctext(((1)?(\"  +\"):('')))calctext(\" + \")"
        ],
        "calctext(((1)?(\"+ some chars except plus sign  +\"):('')))calctext(((1)?(\"  +\"):('')))calctext(\" + \")" => [
            "literalPlusSignLocations" => [16,47,75,96],
            "expectedOutputString" => "calctext(((1)?(\"+ some chars except plus sign  +\"):('')))calctext(((1)?(\"  +\"):('')))calctext(\" + \")"
        ],
    ];
    
    private static $calctextPlusSignConcatCases = [
        // summation of numbers within calctext string
        "calctext(' test ')calctext(' 1 + 2 ')calctext(\" + \")" => [
            "literalPlusSignLocations" => [48],
            "expectedOutputString" => "calctext(' test ')calctext(' 1 *1+1* 2 ')calctext(\" + \")"
        ],
        "calctext(' + ')calctext(' 1 + 2 ')calctext(\" + \")" => [
            "literalPlusSignLocations" => [11, 45],
            "expectedOutputString" => "calctext(' + ')calctext(' 1 *1+1* 2 ')calctext(\" + \")"
        ],
        "calctext(' + ')calctext(' 1 + 2 +3')calctext(\" + \")" => [
            "literalPlusSignLocations" => [11, 47],
            "expectedOutputString" => "calctext(' + ')calctext(' 1 *1+1* 2 *1+1*3')calctext(\" + \")"
        ],
        // concatenation of alphanumeric characters within calctext string
        "calctext('233 + hhh2')" => [
            "literalPlusSignLocations" => [],
            "expectedOutputString" => "calctext('233 *1+1* hhh2')"
        ],
        // double quote present within calctext string
        "calctext('23\"(3 + hh)\"h2')" => [
            "literalPlusSignLocations" => [],
            "expectedOutputString" => "calctext('23\"(3 *1+1* hh)\"h2')"
        ],
        // single quote present within calctext string
        "calctext('23(3 + hh)'h2')" => [
            "literalPlusSignLocations" => [],
            "expectedOutputString" => "calctext('23(3 *1+1* hh)'h2')"
        ],
    ];

    private static $noCalctextCases = [
        "test (' test ')calc (' test ')calc text (\" test \")" => [
            "literalPlusSignLocations" => [],
            "expectedOutputString" => "test (' test ')calc (' test ')calc text (\" test \")"
        ],
        "calc(' test ')textcalc(' test ')(\" test \")" => [
            "literalPlusSignLocations" => [],
            "expectedOutputString" => "calc(' test ')textcalc(' test ')(\" test \")"
        ],
    ];

    public function testPlusSignLiteral()
    {
        $expectedExceptionArr = array();
        foreach (self::$calctextPlusSignAllLiteralCases as $case) {
            $expectedExceptionArr[] = $case["literalPlusSignLocations"];
        }
        $count = 0;
        foreach(self::$calctextPlusSignAllLiteralCases as $caseString => $caseDetails) {
            $exceptionArr = array();
            $caseStringOriginal = $caseString;
            \LogicParser::xConcatJs($caseString, $exceptionArr);
            $this->assertEquals($expectedExceptionArr[$count], $exceptionArr);
            $this->assertEquals($caseStringOriginal, $caseString);
            $count++;
        }
    }

    public function testPlusSignConcat()
    {
        $expectedExceptionArr = array();
        foreach (self::$calctextPlusSignConcatCases as $case) {
            $expectedExceptionArr[] = $case["literalPlusSignLocations"];
        }
        $count = 0;
        foreach(self::$calctextPlusSignConcatCases as $caseString => $caseDetails) {
            $exceptionArr = array();
            $caseStringOriginal = $caseString;
            \LogicParser::xConcatJs($caseString, $exceptionArr);
            $this->assertEquals($expectedExceptionArr[$count], $exceptionArr);
            $this->assertEquals(self::$calctextPlusSignConcatCases[$caseStringOriginal]["expectedOutputString"], $caseString);
            $count++;
        }
    }

    public function testNoMatch()
    {
        foreach(self::$noCalctextCases as $caseString => $caseDetails) {
            $exceptionArr = array();
            \LogicParser::xConcatJs($caseString, $exceptionArr);
            $this->assertEmpty($exceptionArr);
            $this->assertEquals(self::$noCalctextCases[$caseString]["expectedOutputString"], $caseString);
        }
    }
}
