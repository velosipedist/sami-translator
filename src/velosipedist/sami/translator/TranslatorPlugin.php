<?php
/**
 * UMI.Framework (http://umi-framework.ru/)
 *
 * @link      http://github.com/Umisoft/framework for the canonical source repository
 * @copyright Copyright (c) 2007-2013 Umisoft ltd. (http://umisoft.ru/)
 * @license   http://umi-framework.ru/license/bsd-3 BSD-3 License
 */
namespace velosipedist\sami\translator;

use Gettext\Entries;
use Gettext\Extractors\Mo;
use Gettext\Extractors\Po as PoExtractor;
use Gettext\Generators\Po as PoGenerator;
use Gettext\Translation;
use Sami\Project;
use Sami\Sami;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Underscore\Types\String;
use velosipedist\sami\translator\extractors\PhpdocExtractor;

/**
 * Injects ability to output localized versions of every parsed lib version
 */
class TranslatorPlugin
{
    const ID = 'umi\sami\translator\TranslatorPlugin';
    const PROTOCOL = 'doclocal';

    /**
     * @var PhpdocExtractor $extractor
     */
    protected static $docExtractor;
    /**
     * @var PoExtractor $poExtractor
     */
    protected static $poExtractor;
    protected static $generator;
    protected $container;
    protected $ignoreDocPatterns = [];
    protected $commonBuildDir;
    protected $useContextComments = true;

    /**
     * @var string $translationsPath
     */
    private $translationsPath;
    /**
     * @var string $language
     */
    private $language;

    /**
     * @param $language
     * @param Sami $container
     * @param array $options
     */
    function __construct($language, Sami $container, $options = [])
    {
        $this->container = $container;
        $this->language = $language;
        $this->filesys = new Filesystem();
        $this->commonBuildDir = $container['build_dir'];

        $this->translationsPath = isset($options['translationsPath'])
            ? $this->parseTranslationsPath($options['translationsPath'])
            : $this->defaultTranslationsPath($container);

        $this->ignoreDocPatterns = isset($options['ignoreDocPatterns'])
            ? $options['ignoreDocPatterns']
            : [];
        //        $this->translationsPath = $this->normalizePath($this->translationsPath);

        $container['build_dir'] .= '/' . $language;
        $container['cache_dir'] .= '/' . $language;

        // substitute any iterator passed to Sami
        $finder = $container['files'];
        if (is_string($finder)) {
            $finder = Finder::create()
                ->in($finder);
        }
        $iterator = new MultilangFilesIterator($finder);
        $container['files'] = $iterator;

        // setup stream wrapper
        TranslateStreamWrapper::setupTranslatorPlugin($this);
        if (isset($options['useContextComments'])) {
            $this->useContextComments = (bool) $options['useContextComments'];
        }
    }

    /**
     * .po generator singleton
     *
     * @return PoGenerator
     */
    private static function generator()
    {
        if (is_null(self::$generator)) {
            self::$generator = new PoGenerator();
        }
        return self::$generator;
    }

    /**
     * Phpdoc extractor singleton
     *
     * @return PhpdocExtractor
     */
    private function docExtractor()
    {
        if (is_null(self::$docExtractor)) {
            $e = self::$docExtractor = new PhpdocExtractor();
            $e::$ignoreDocPatterns = $this->ignoreDocPatterns;
            $e::$useCommentedCodeAsEntriesComments = $this->useContextComments;
        }
        return self::$docExtractor;
    }

    /**
     * Open source code, extracts docs for template rewrite, then finds possible translations.
     *
     * @param $path
     *
     * @throws \RuntimeException
     * @return string
     */
    public function parseDocsFromFile($path)
    {
        $fileContents = file_get_contents($path);
        $namespace = $this->detectSourceNamespace($fileContents);

        $translationsPath = $this->resolveVersionedTranslationsPath() . '/' . str_replace('\\', '/', $namespace);
        $this->filesys->mkdir($translationsPath, 0777);

        // search for existing translations
        $basename = String::sliceTo(basename($path), '.php');
        $templateFileName = $translationsPath . '/' . $basename . '.pot';

        $docExtractor = $this->docExtractor();
        $entriesTemplate = $docExtractor::extract($path);

        $translationsFileName = $translationsPath . '/' . $basename . '.' . $this->language . '.po';
        $compiledTranslationsFileName = $translationsPath . '/' . $basename . '.' . $this->language . '.mo';
        $moExtractor = new Mo();

        try {
            $entriesTranslated = $moExtractor->extract($compiledTranslationsFileName);
        } catch (\InvalidArgumentException $e) {
            // if there was no template — create new & add default translations
            $entriesTranslated = new Entries();
            self::generator()
                ->generateFile($entriesTranslated, $translationsFileName);
        }

        $replaces = [];
        foreach ($docExtractor::getPhpDocs() as $phpDoc) {
            /** @var $translation Translation */
            $translation = $entriesTranslated->find(null, $phpDoc);
            if ($translation) {
                $replaces[$phpDoc] = $translation->getTranslation();
            }
        }

        //todo diff with rest of outdated (nonexistent anymore) entries
        // save new & remove outdated entries
        $generated = self::generator()
            ->generateFile($entriesTemplate, $templateFileName);
        if (!$generated) {
            throw new \RuntimeException("Generation of $templateFileName failed");
        }

        return strtr($fileContents, $replaces);
    }

    /**
     * @param Sami $container
     *
     * @return string
     */
    private function defaultTranslationsPath(Sami $container)
    {
        $defaultPath = getcwd() . '/../translations/';
        if (isset($container['version'])) {
            $defaultPath .= '%version%/';
        }
        return $defaultPath;
    }

    /**
     * @param $path
     *
     * @return mixed
     */
    public function normalizePath($path)
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @param string $src
     *
     * @throws ParseException
     * @return string
     */
    private function detectSourceNamespace($src)
    {
        //todo enforce checking, <?php at beginning, no commented namespace line
        preg_match('/namespace\s+(?P<ns>[0-9a-z\x5c_]+)\s*;\s*\r?\n/i', $src, $matches);
        if (!isset($matches['ns'])) {
            throw new ParseException("No namespace detected in: \n$src", ParseException::NAMESPACE_NOT_FOUND);
        }
        return $matches['ns'];
    }

    /**
     * @param $fileContents
     *
     * @return array
     */
    protected static function toLines($fileContents)
    {
        return preg_split("/((\r?\n)|(\r\n?))/", $fileContents);
    }

    /**
     * @param $path
     *
     * @return string
     */
    private function parseTranslationsPath($path)
    {
        return str_replace('%build%', $this->commonBuildDir, $path);
    }

    /**
     * @return string
     */
    public function resolveVersionedTranslationsPath()
    {
        /** @var Project $project */
        $project = $this->container['project'];

        $v = is_null($version = $project->getVersion()) ? $this->container['version'] : $version->getName();
        return str_replace('%version%', $v, $this->translationsPath);
    }
}
