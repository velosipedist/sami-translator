<?php
namespace tests\unit;

use Sami\Sami;
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
        $this->markTestIncomplete('debug me');
        $process = $this->createProcess();

        try {
            $code = $process->run();
            print "[OUTPUT] " . $process->getOutput();
        } catch (\Exception $e) {
            $this->fail('Fail of process: ' . $e->getMessage());
        }

        $this->assertEquals(0, $code, 'Process must end up successfully');
        $this->assertFileExists(__DIR__ . '/../mock/translations', 'Translations dir must be created');
        $this->assertFileExists(__DIR__ . '/../mock/translations/master', 'Translations output must be created');
        $this->assertFileExists(
            __DIR__ . '/../mock/translations/master/mock/ru.po',
            'Translations output must be saved'
        );
    }

    public function testFileIterator()
    {
        $sami = $this->setupSami(__DIR__ . '/../mock/src');

        // this decorating trick invokes inside TranslatePlugin
        $translator = new TranslatorPlugin('ru', $sami);
        $i = $sami['files'];

        $this->assertGreaterThan(0, iterator_count($i), 'Iterator must see .php files in src directories');
        foreach ($i as $file) {
            $this->assertStringStartsWith('doclocal:', $file);
            // Sami relies on straight file_get_contents
            $content = file_get_contents($file);
            $this->assertStringStartsWith('<?php', $content);
        }
        $this->assertFileExists(__DIR__ . '/../mock/translations/master/mock/CompleteDocumentedClass.ru.pot');
    }

    public function testTranslationsPath()
    {
        $sami = $this->setupSami(__DIR__ . '/../mock/src');

        $translator = new TranslatorPlugin('ru', $sami,[
            'translationsPath'=>__DIR__ . '/../mock/po'
        ]);
        $i = $sami['files'];

        foreach ($i as $file) {
            // Sami relies on straight file_get_contents
            $content = file_get_contents($file);
            $this->assertEquals(
                file_get_contents(__DIR__.'/../mock/translated/'.basename($file).'.txt'),
                $content,
                'Source must be translated'
            );
        }
    }

    /**
     * @param $path
     *
     * @return Sami
     */
    protected function setupSami($path)
    {
        chdir($path);
        // empty finder - to initial container
        $internalFinder = Finder::create()
            ->in($path);
        $sami = new Sami($internalFinder);
        return $sami;
    }

}
