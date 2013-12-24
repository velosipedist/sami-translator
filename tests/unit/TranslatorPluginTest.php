<?php
namespace tests\unit;

use Sami\Sami;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use velosipedist\sami\translator\extractors\PhpdocExtractor;
use velosipedist\sami\translator\ParseException;
use velosipedist\sami\translator\TranslatorPlugin;

/**
 * Class TranslatorPluginTest
 */
class TranslatorPluginTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/../mock/translations');
        $fs->remove(__DIR__ . '/../mock/build');
        $fs->remove(__DIR__ . '/../mock/cache');
        $fs->remove(__DIR__ . '/../mock/runtime');
    }

    /**
     * @return Process
     */
    private function createProcess()
    {
        //todo just find vendor as root
        $cwd = __DIR__ . '/../mock/src';
        $sami = realpath($cwd . '/../../../../../bin/sami.php');
        $config = realpath($cwd . '/../../../demo/config-ru.php');
        $p = new Process(
            'php "' . $sami . '" update "' . $config . '"',
            $cwd
        );

        return $p;
    }

    public function testIteratorAsString()
    {
        $sami = new Sami(__DIR__ . '/../mock/src');
        $plugin = new TranslatorPlugin('ru', $sami);
        $this->assertInstanceOf('velosipedist\sami\translator\MultilangFilesIterator', $sami['files']);
    }

    public function testRunSuccessfully()
    {
        $process = $this->createProcess();

        try {
            $code = $process->run();
            print "[OUTPUT] " . $process->getOutput();
        } catch (\Exception $e) {
            print "[OUTPUT] " . $process->getOutput();
            $this->fail('Fail of process: ' . $e->getMessage());
        }

        $this->assertEquals(0, $code, 'Process must end up successfully');
    }

    public function testFileIterator()
    {
        $sami = $this->setupSami();

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
        // version is master by default
        $dirExpected = __DIR__ . '/../mock/translations/master/mock';
        $this->assertFileExists($dirExpected . '/CompleteDocumentedClass.ru.po');
        $this->assertFileExists($dirExpected . '/CompleteDocumentedClass.pot');
    }

    public function testTranslationsPathPlaceholder()
    {
        $sami = $this->setupSami();

        $translator = new TranslatorPlugin('ru', $sami, [
            'translationsPath' => '%build%/../translations/placeholded'
        ]);
        $i = $sami['files'];
        foreach ($i as $file) {
            // Sami relies on straight file_get_contents
            $content = file_get_contents($file);
        }
        $dirExpected = __DIR__ . '/../mock/translations/placeholded';
        $this->assertTrue(
            is_dir($dirExpected),
            'Placeholded dir must be created'
        );
        $this->assertFileExists(
            $dirExpected . '/mock/CompleteDocumentedClass.pot',
            'Placeholded dir must contain translations template'
        );
    }

    public function testTranslationsPathVersionPlaceholder()
    {
        $sami = $this->setupSami();
        $sami['build_dir'] = __DIR__ . '/../mock/build/%version%';

        $sami['version'] = 'master';
        $dirExpected = __DIR__ . '/../mock/translations/version-placeholded/master';

        $translator = new TranslatorPlugin('ru', $sami, [
            'translationsPath' => '%build%/../../translations/version-placeholded/%version%'
        ]);
        $i = $sami['files'];
        foreach ($i as $file) {
            // Sami relies on straight file_get_contents
            $content = file_get_contents($file);
        }
        $this->assertTrue(
            is_dir($dirExpected),
            'Version dir must be created'
        );
        $this->assertFileExists(
            $dirExpected . '/mock/CompleteDocumentedClass.pot',
            'Version dir must contain translations template'
        );

        // and now for something different
        $sami['version'] = 'slave';
        $dirExpected = __DIR__ . '/../mock/translations/version-placeholded/slave';
        foreach ($i as $file) {
            // Sami relies on straight file_get_contents
            $content = file_get_contents($file);
        }
        $this->assertTrue(
            is_dir($dirExpected),
            'Version2 dir must be created'
        );
        $this->assertFileExists(
            $dirExpected . '/mock/CompleteDocumentedClass.pot',
            'Version2 dir must contain translations template'
        );
    }

    public function testDetectNamespace()
    {
        $sami = $this->setupSami(__DIR__ . '/../mock/namespaces');

        $translator = new TranslatorPlugin('ru', $sami);

        try {
            foreach ($sami['files'] as $file) {
                // Sami relies on straight file_get_contents
                $content = file_get_contents($file);
            }
        } catch (ParseException $e) {
            if ($e->getCode() == ParseException::NAMESPACE_NOT_FOUND) {
                $this->fail("Namespace must be found in $file");
            }
        }
    }

    public function testDiffEntries()
    {
        $extractor = new PhpdocExtractor();
        $classesDir = __DIR__ . '/../mock/src/diffs/';
        $tempClassFile = __DIR__ . '/../runtime/Diffs.php';
        $sami = $this->setupSami(dirname($tempClassFile));
        $entriesInit = $extractor->extract($classesDir . 'Init.php');

        // load default class data
        file_put_contents($tempClassFile, file_get_contents($classesDir . 'Init.php'));
        $translator = new TranslatorPlugin('en', $sami);
    }

    /**
     * @param $path
     *
     * @return Sami
     */
    protected function setupSami($path = false)
    {
        if ($path === false) {
            $path = __DIR__ . '/../mock/src';
        }
        chdir($path);
        // empty finder - to initial container
        $internalFinder = Finder::create()
            ->in($path)
            ->exclude('.git')
            ->exclude('.idea')
            ->exclude('vendor')
            ->exclude('tests')
            ->exclude('docs')
            ->name('*.php');
        $sami = new Sami($internalFinder);
        $sami['build_dir'] = __DIR__ . '/../mock/build/';
        $sami['cache_dir'] = __DIR__ . '/../mock/cache/';
        $sami['default_opened_level'] = 1;
        return $sami;
    }

}
