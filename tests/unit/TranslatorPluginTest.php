<?php
namespace tests\unit;

use Sami\Sami;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use umi\sami\translator\MultilangFilesIterator;
use umi\sami\translator\TranslatorPlugin;

/**
 * Class TranslatorPluginTest
 */
class TranslatorPluginTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {

    }

    public function tearDown()
    {
//        $fs = new Filesystem();
//        $fs->remove(__DIR__ . '/../build');
    }

    /**
     * @return Process
     */
    private function createProcess()
    {
        $cwd = __DIR__ . '/../mock/src';
        $p = new Process(
            'php ../../../vendor/sami/sami/sami.php update ../../../config.php',
            $cwd,
            ['sami.testdir' => '.']
        );

        return $p;
    }

    public function testRunSuccessfully()
    {
        $process = $this->createProcess();

        try {
            $code = $process->run();
            print "[OUTPUT] " . $process->getOutput();
        } catch (\Exception $e) {
            $this->fail('Fail of process: ' . $e->getMessage());
        }

        $this->assertEquals(0, $code, 'Process must end up successfully');
        $this->assertFileExists(__DIR__ . '/../mock/translations', 'Translations dir must be created');
        $this->assertFileExists(__DIR__.'/../mock/translations/master', 'Translations output must be created');
        $this->assertFileExists(__DIR__.'/../mock/translations/master/mock/ru.po', 'Translations output must be saved');
    }

    public function testFileIterator()
    {
        chdir(__DIR__ . '/../mock/src');
        // empty finder - to initial container
        $internalFinder = Finder::create()
            ->in(__DIR__ . '/../mock/src');
        $sami = new Sami($internalFinder);
        $this->assertFileNotExists(__DIR__.'/../mock/translations/master/mock/ru.po');

        // this decorating trick invokes inside TranslatePlugin
        $i = new MultilangFilesIterator($internalFinder);
        $i->setTranslator(new TranslatorPlugin('ru', $sami));

        $this->assertGreaterThan(0, iterator_count($i), 'Iterator must see .php files in src directories');
        foreach ($i as $file) {
            $this->assertStringStartsWith('doclocal:',$file);
            $content = file_get_contents($file);
            $this->assertStringStartsWith('<?php', $content);
        }
    }

    public function testDocSubstitution()
    {

    }

}
