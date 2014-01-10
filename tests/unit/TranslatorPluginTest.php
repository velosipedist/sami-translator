<?php
namespace tests\unit;

use Sami\Parser\Filter\TrueFilter;
use Sami\Project;
use Sami\Sami;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use velosipedist\sami\translator\ParseException;
use velosipedist\sami\translator\TranslatorPlugin;

/**
 * Class TranslatorPluginTest
 */
class TranslatorPluginTest extends \PHPUnit_Framework_TestCase
{
    /** @var  Filesystem */
    private $fs;

    public function setUp()
    {
        $this->fs = new Filesystem();
        try {
            $this->fs->remove(__DIR__ . '/../fixtures/translations');
            $this->fs->remove(__DIR__ . '/../runtime');
        } catch (\Exception $e) {
        }
    }

    /**
     * @param string $path Where source files located
     * @return Sami
     */
    protected function createSami($path)
    {
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
        $sami['filter'] = $sami->share(
            function () {
                return new TrueFilter();
            }
        );
        $sami['build_dir'] = __DIR__ . '/../runtime/build/';
        $sami['cache_dir'] = __DIR__ . '/../runtime/cache/';
        $sami['default_opened_level'] = 1;

        return $sami;
    }

    /**
     * @return Process
     */
    private function createProcess()
    {
        //todo just find vendor as root
        $cwd = __DIR__ . '/../fixtures/src';
        $vendors = realpath(__DIR__ . '/../../vendor');
        $sami = realpath($vendors . '/bin/sami.php');
        $config = realpath($cwd . '/../../demo/config-ru.php');
        $p = new Process(
            'php "' . $sami . '" update "' . $config . '"',
            $cwd
        );

        return $p;
    }

    /**
     * Test passing sources path as file iterator to Sami
     */
    public function testIteratorAsString()
    {
        $sami = new Sami(__DIR__ . '/../fixtures/src');
        $plugin = new TranslatorPlugin('ru', $sami);
        $this->assertInstanceOf('velosipedist\sami\translator\MultilangFilesIterator', $sami['files']);
    }

    /**
     * Command line usage check
     */
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

    /**
     * Check plugin ability to substitute source file stream with translation result.
     * By default, translatios path will be outside of translated sources, on same level
     */
    public function testFileIterator()
    {
        $sami = $this->createSami(__DIR__ . '/../fixtures/src');

        // this decorating trick invokes inside TranslatePlugin
        $translator = new TranslatorPlugin('ru', $sami, [
            'translateOnly' => false,
        ]);
        $i = $sami['files'];

        $this->assertGreaterThan(0, iterator_count($i), 'Iterator must see .php files in src directories');
        foreach ($i as $file) {
            $this->assertStringStartsWith('doclocal:', $file);
            // Sami relies on straight file_get_contents
            $content = file_get_contents($file);
            $this->assertStringStartsWith('<?php', $content);
        }
        // version is master by default
        $dirExpected = __DIR__ . '/../fixtures/translations/mock';
        $this->assertFileExists($dirExpected . '/CompleteDocumentedClass.pot');
        $this->assertFileExists($dirExpected . '/CompleteDocumentedClass.ru.po');
    }

    /**
     * If %build% token specified in translationsPath, translations output must be relative to it
     */
    public function testTranslationsPathPlaceholder()
    {
        $sami = $this->createSami(__DIR__ . '/../fixtures/src');

        $translator = new TranslatorPlugin('ru', $sami, [
            'translationsPath' => '%build%/../translations/placeholded',
            'translateOnly'    => false,
        ]);
        $i = $sami['files'];
        foreach ($i as $file) {
            $content = file_get_contents($file);
        }
        $dirExpected = __DIR__ . '/../runtime/translations/placeholded';
        $this->assertTrue(
            is_dir($dirExpected),
            'Placeholded dir must be created'
        );
        $this->assertFileExists(
            $dirExpected . '/mock/CompleteDocumentedClass.pot',
            'Placeholded dir must contain translations template'
        );
    }

    /**
     * When %version% specified a build dir, docs must be created in corresponding subdirectory
     */
    public function testTranslationsPathVersionPlaceholder()
    {
        $sami = $this->createSami(__DIR__ . '/../fixtures/src');
        $sami['build_dir'] = __DIR__ . '/../runtime/build/%version%';

        $sami['version'] = 'master';
        $dirExpected = __DIR__ . '/../runtime/translations/version-placeholded/master';

        $translator = new TranslatorPlugin('ru', $sami, [
            'translationsPath' => '%build%/../../translations/version-placeholded/%version%',
            'translateOnly'    => false,
        ]);
        $i = $sami['files'];
        foreach ($i as $file) {
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
        $dirExpected = __DIR__ . '/../runtime/translations/version-placeholded/slave';
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
        $sami = $this->createSami(__DIR__ . '/../fixtures/namespaces');

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

    public function testSignaturesStrategy()
    {
        $sami = $this->createSami(__DIR__ . '/../fixtures/src');

        //todo move to singleton ?
        $sami[TranslatorPlugin::ID] = new TranslatorPlugin('ru', $sami, [
            'messageKeysStrategy' => TranslatorPlugin::USE_SIGNATURES_AS_KEYS,
            'translateOnly'       => false,
        ]);
        /** @var $project Project */
        $project = $sami['project'];

        $project->update();
        $expectedDir = __DIR__ . '/../fixtures/translations/mock/';
        $this->assertTrue(is_dir($expectedDir));
        $this->assertFileExists($expectedDir . 'CompleteDocumentedClass.pot');
        $this->assertFileExists($expectedDir . 'CompleteDocumentedClass.ru.po');
    }

    public function testInheritdocs()
    {
        $sami = $this->createSami(__DIR__ . '/../fixtures/src');

        $sami[TranslatorPlugin::ID] = new TranslatorPlugin('ru', $sami, [
            'messageKeysStrategy' => TranslatorPlugin::USE_SIGNATURES_AS_KEYS,
            'translateOnly'       => false,
        ]);
        /** @var $project Project */
        $project = $sami['project'];

        $project->update();
        $pot = file_get_contents(__DIR__ . '/../fixtures/translations/mock/' . 'CompleteDocumentedClass.pot');
        $this->assertNotRegExp('/@inheritdoc/m', $pot, 'Inheritdocs must be resolved');
    }

    public function testMultilang()
    {
        $sami = $this->createSami(__DIR__ . '/../fixtures/src');
        $expectedDir = __DIR__ . '/../fixtures/translations/mock/';

        $translator = new TranslatorPlugin('ru', $sami, [
            'messageKeysStrategy' => TranslatorPlugin::USE_SIGNATURES_AS_KEYS,
            'translateOnly'       => false,
        ]);
        $sami[TranslatorPlugin::ID] = $translator;
        $sami['project']->update();

        $this->fs->remove($sami['cache_dir']);

        // drop all container common dependencies
        unset($sami);

        // again, in english
        $samiEn = $this->createSami(__DIR__ . '/../fixtures/src');
        $translator = new TranslatorPlugin('en', $samiEn, [
            'messageKeysStrategy' => TranslatorPlugin::USE_SIGNATURES_AS_KEYS,
            'translateOnly' => false,
        ]);
        $samiEn[TranslatorPlugin::ID] = $translator;
        $samiEn['project']->update();

        $this->assertTrue(is_dir($expectedDir));
        $this->assertFileExists($expectedDir . 'CompleteDocumentedClass.pot');
        $this->assertFileExists($expectedDir . 'CompleteDocumentedClass.ru.po');
        $this->assertFileExists($expectedDir . 'CompleteDocumentedClass.en.po');
    }

}
