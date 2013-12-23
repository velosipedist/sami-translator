<?php
namespace tests\unit;

use Gettext\Entries;
use umi\sami\translator\extractors\PhpdocExtractor;

class ExtractorTest extends \PHPUnit_Framework_TestCase
{

    public function testIgnorePatterns()
    {
        $ex = new PhpdocExtractor();
        $entries = new Entries();
        $ex->parse(__DIR__.'/../mock/src/CompleteDocumentedClass.php', $entries);
        $allDocs = count($entries->getArrayCopy());

        $ex::$ignoreDocPatterns = ['/@inheritdoc/'];
        $entries = new Entries();
        $ex->parse(__DIR__.'/../mock/src/CompleteDocumentedClass.php', $entries);
        $entriesArray = $entries->getArrayCopy();
        $this->assertCount($allDocs-1,$entriesArray);
    }
}
