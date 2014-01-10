<?php
namespace tests\unit;

use Gettext\Extractors\Po;

class ExtractorTest extends \PHPUnit_Framework_TestCase
{
    public function testMultiline()
    {
        $this->markTestIncomplete('Parse multiline po files?');
        $docExpected = [
            "single line id" => "First Line\nsecond line",
            "multi line id start\nmulti line id end" => "Multiline First Line\nmultiline second line",
        ];
        $extractor = new Po();
        $this->assertEquals(
            $docExpected,
            $extractor->extract(__DIR__.'/../fixtures/po/multiline.po')->getArrayCopy()
        );
    }
}
