<?php
//todo improve performance with static fields, but now need to move members to non-static
namespace velosipedist\sami\translator;

use Gettext\Entries;
use Gettext\Extractors\Mo;
use Gettext\Extractors\Po as PoExtractor;
use Gettext\Generators\Po as PoGenerator;
use Gettext\Translation;
use Sami\Parser\CodeParser;
use Sami\Project;
use Sami\Reflection\ClassReflection;
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
    const USE_PHPDOCS_AS_KEYS = 1;
    const USE_SIGNATURES_AS_KEYS = 2;

    /**
     * @var PhpdocExtractor $extractor
     */
    protected static $docExtractor;
    /**
     * @var PoExtractor $poExtractor
     */
    protected static $poExtractor;
    protected static $generator;
    protected static $moExtractor;
    protected $container;
    /**
     * @var $commonBuildDir
     */
    protected $commonBuildDir;
    /**
     * @var bool $translateOnly If false, plugin will also create/regenerate .pot files
     */
    protected $translateOnly = false;
    /**
     * @var int $messageKeysStrategy
     */
    protected $messageKeysStrategy;

    /**
     * @var string $translationsPath
     */
    private static $translationsPath;
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
        $this->commonBuildDir = $container['build_dir'];
        $this->container['build_dir'] .= '/' . $language;
        $this->container['cache_dir'] .= '/' . $language;

        $this->filesys = new Filesystem();

        self::$translationsPath = isset($options['translationsPath'])
            ? $this->parseTranslationsPath($options['translationsPath'])
            : $this->defaultTranslationsPath($container);

        $this->messageKeysStrategy = isset($options['messageKeysStrategy'])
            ? $options['messageKeysStrategy']
            : self::USE_PHPDOCS_AS_KEYS;

        if (isset($options['translateOnly'])) {
            $this->translateOnly = $options['translateOnly'];
        }

        self::$generator = new PoGenerator();
        self::$poExtractor = new PoExtractor();
        self::$moExtractor = new Mo();

        switch ($this->messageKeysStrategy) {
            // if we use raw phpdocs as keys, we need to substitute iterator & intercept files input
            case self::USE_PHPDOCS_AS_KEYS:
                $this->usePhpdocsStrategy($options);
                break;
            case self::USE_SIGNATURES_AS_KEYS:
                $this->useSignaturesStrategy($options);
                break;
        }
    }

    /**
     * Open source code, extracts docs for template rewrite, then finds possible translations.
     * @param $path
     * @throws \RuntimeException
     * @return string
     */
    public function translateFile($path)
    {
        $fileContents = file_get_contents($path);
        $namespace = $this->detectSourceNamespace($fileContents);
        $className = String::sliceTo(basename($path), '.php');
        $phpdocExtractor = self::$docExtractor;
        $entriesTranslated = $this->localizeEntries(
            $namespace,
            $className,
            $phpdocExtractor
                ->extract($path)
        );

        $replaces = [];

        foreach ($phpdocExtractor::getPhpDocs() as $phpDoc) {
            /** @var $translation Translation */
            $translation = $entriesTranslated->find(null, $phpDoc);
            if ($translation) {
                $replaces[$phpDoc] = $translation->getTranslation();
            }
        }

        return strtr($fileContents, $replaces);
    }

    /**
     * Process pairs of message-Reflection, replacing with current language strings
     * @param ClassReflection $class
     * @param array $messages
     * @return array
     */
    public function translateClassReflection(ClassReflection $class, $messages)
    {
        $entries = new Entries();
        foreach ($messages as $msgid => $message) {
            $tr = $entries->insert(null, $msgid);
            $tr->setTranslation($message[0]);
        }
        $entries = $this->localizeEntries($class->getNamespace(), $class->getShortName(), $entries);
        foreach ($messages as $msgid => $message) {
            if ($translation = $entries->find(null, $msgid)) {
                $message[1]->setDocComment($translation->getTranslation());
            }
        }

        return $messages;
    }

    /**
     * @param $namespace
     * @param $className
     * @return string
     */
    private function findMoFile($namespace, $className)
    {
        return $this->resolveVersionedRelativePath($namespace, $className) . '.' . $this->language . '.mo';
    }

    /**
     * @param $namespace
     * @param $className
     * @return string
     */
    private function findPoFile($namespace, $className)
    {
        return $this->resolveVersionedRelativePath($namespace, $className) . '.' . $this->language . '.po';
    }

    /**
     * @param $namespace
     * @param $className
     * @return string
     */
    private function findPotFile($namespace, $className)
    {
        return $this->resolveVersionedRelativePath($namespace, $className) . '.pot';
    }

    /**
     * Used when no translationsPath specified
     * @return string
     */
    private function defaultTranslationsPath()
    {
        return getcwd() . '/../translations/';
    }

    /**
     * @param $path
     * @return string
     */
    public function normalizePath($path)
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @param string $src
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
     * @return array
     */
    protected static function toLines($fileContents)
    {
        return preg_split("/((\r?\n)|(\r\n?))/", $fileContents);
    }

    /**
     * @param $path
     * @return string
     */
    private function parseTranslationsPath($path)
    {
        return str_replace('%build%', $this->commonBuildDir, $path);
    }

    /**
     * @param $namespace
     * @param $className
     * @return string
     */
    public function resolveVersionedRelativePath($namespace, $className = null)
    {
        /** @var Project $project */
        $project = $this->container['project'];

        $v = is_null($version = $project->getVersion()) ? $this->container['version'] : $version->getName();
        $path = str_replace('%version%', $v, self::$translationsPath) . '/' . str_replace('\\', '/', $namespace);
        if (is_string($className)) {
            $path .= '/' . $className;
        }

        return $path;
    }

    /**
     * Set plugin to "key=phpdoc" mode
     * @param $options
     */
    private function usePhpdocsStrategy($options)
    {
        // substitute any iterator passed to Sami
        $finder = $this->container['files'];
        if (is_string($finder)) {
            $finder = Finder::create()
                ->in($finder);
        }
        $iterator = new MultilangFilesIterator($finder);
        $this->container['files'] = $iterator;

        $dExtractor = self::$docExtractor = new PhpdocExtractor();
        $dExtractor::setTranslator($this);

        // setup stream wrapper
        TranslateStreamWrapper::setupTranslatorPlugin($this);
    }

    /**
     * @param array $options
     */
    private function useSignaturesStrategy($options)
    {
        $this->container['traverser']->addVisitor(new ClassVisitor($this->container));
    }

    /**
     * Turn passed entries translations into current language
     * @param $namespace
     * @param $className
     * @param Entries $entries
     * @return Entries
     */
    private function localizeEntries($namespace, $className, Entries $entries)
    {
        $moFileName = $this->findMoFile($namespace, $className);
        try {
            $translatedEntries = self::$moExtractor
                ->extract($moFileName);
        } catch (\InvalidArgumentException $e) {
            // if no translations, leave entries as-is
            $translatedEntries = $entries;
        }

        if ($translatedEntries !== $entries) {
            foreach ($entries as $entry) {
                /** @var $entry Translation */
                if ($translation = $translatedEntries->find(null, $entry->getOriginal())) {
                    $entry->setTranslation($translation->getTranslation());
                }
            }
        }

        if (!$this->translateOnly) {
            $this->updateTranslationFiles($namespace, $className, $entries);
        }

        return $entries;
    }

    /**
     * Create or update .pot files filled with actual keys
     * @param $namespace
     * @param $className
     * @param Entries $entries
     * @throws \RuntimeException
     */
    private function updateTranslationFiles($namespace, $className, Entries $entries)
    {
        $templateFileName = $this->findPotFile($namespace, $className);
        try {
            $extractor = new PoExtractor();
            $entriesPrevious = $extractor->extract($templateFileName);
        } catch (\InvalidArgumentException $e) {
            $entriesPrevious = $entries;
            $this->filesys->mkdir(dirname($templateFileName));
        }

        if (!file_exists($po = $this->findPoFile($namespace, $className))) {
            self::$generator->generateFile($entries, $po);
        }

        $entriesToSave = $entries;
        if ($entriesPrevious !== $entries) {
            // ArrayObject dynamically updates itself for iterating, so we need copy to save
            $entriesToSave = clone $entries;
            foreach ($entries as $entry) {
                /** @var $entry Translation */
                if (!$translation = $entriesPrevious->find(null, $entry->getOriginal())) {
                    $newEntry = $entriesToSave->insert(null, $entry->getOriginal());
                    $newEntry->setTranslation($entry->getTranslation());
                }
            }
        }

        $result = self::$generator
            ->generateFile($entriesToSave, $templateFileName);
        if (!$result) {
            throw new \RuntimeException("Failed to generate $templateFileName");
        }
    }

    public function getReflectionByFileName($file)
    {
        /** @var $parser CodeParser */
        $parser = $this->container['code_parser'];
        $context = $parser->getContext();
        $context->enterFile((string) $file, '');
        $parser->parse(file_get_contents($file));
        return current($context->leaveFile());
//        throw new \UnexpectedValueException("Reflection for $file not found");
    }
}
