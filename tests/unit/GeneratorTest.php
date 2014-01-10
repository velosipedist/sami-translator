<?php
namespace tests\unit;

use Gettext\Entries;

class GeneratorTest extends \PHPUnit_Framework_TestCase
{
    public function testMultiline()
    {
        //        $this->markTestIncomplete(
        //            "Check all multiline strings generation cases to be compatible with any PoEdit build"
        //        );
        $docs = [
            "single line id" => "First Line\nsecond line",
            "multi line id start\nmulti line id end" => "Multiline First Line\nmultiline second line",
        ];
        $entries = new Entries($docs);
        $generator = new \Gettext\Generators\Po();
        $this->assertEquals(
            file_get_contents(__DIR__.'/../fixtures/po/multiline.po'),
            $generator->generate($entries)
        );
    }
}
