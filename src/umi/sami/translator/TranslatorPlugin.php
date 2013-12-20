<?php
/**
 * UMI.Framework (http://umi-framework.ru/)
 *
 * @link      http://github.com/Umisoft/framework for the canonical source repository
 * @copyright Copyright (c) 2007-2013 Umisoft ltd. (http://umisoft.ru/)
 * @license   http://umi-framework.ru/license/bsd-3 BSD-3 License
 */
namespace umi\sami\translator;

use Gettext\Entries;
use Gettext\Extractors\Po;
use Gettext\Translation;
use Sami\Sami;
use Symfony\Component\Filesystem\Filesystem;
use Underscore\Types\Arrays;
use Underscore\Types\String;

/**
 * Injects ability to output localized versions of every parsed lib version
 */
class TranslatorPlugin
{
    const ID = 'umi\sami\translator\TranslatorPlugin';
    const PROTOCOL = 'doclocal';
    protected $container;

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
        $this->translationsPath = isset($options['translationsPath'])
            ? $options['translationsPath']
            : $this->defaultTranslationsPath($container);
        $this->translationsPath = $this->normalizePath($this->translationsPath);
        $container['build_dir'] .= '/' . $language;
        $container['cache_dir'] .= '/' . $language;

        if (!is_dir($this->translationsPath)) {
            mkdir($this->translationsPath, 0777, true);
        }

        // substitute any iterator passed to Sami
        $iterator = new MultilangFilesIterator($container['files']);
        $container['files'] = $iterator;

        // setup stream wrapper
        TranslateStreamWrapper::setupTranslatorPlugin($this);
    }

    /**
     * Open source code, replace already translated original docs with .po entries.
     *
     * @param $path
     *
     * @throws \RuntimeException
     * @return string
     */
    public function parseDocsFromFile($path)
    {
        $fileContents = file_get_contents($path);

        $allTokens = token_get_all($fileContents);

        $lines = self::toLines($fileContents);

        $namespace = $this->detectSourceNamespace($allTokens, $lines);

        $translationsPath = $this->translationsPath . '/' . str_replace('\\', '/', $namespace);
        if (!is_dir($translationsPath)) {
            @mkdir($translationsPath, 0777, true);
        }

        $generator = new \Gettext\Generators\Po();

        $poFileName = $translationsPath . '/' . $this->language . '.po';
        $poEntries = $this->setupPoEntries($poFileName);

        $phpDocs = [];
        foreach ($allTokens as $tok) {
            //todo ignore @inheritdoc & all «@marker»-only comments
            if ($tok[0] == T_DOC_COMMENT) {
                $phpDocs[$tok[2]] = $tok[1];
            }
        }

        $replaces = [];
        $context = String::sliceTo(basename($path), '.php');

        foreach ($phpDocs as $lineNum => $phpDoc) {
            /** @var $translation Translation */
            $translation = $poEntries->find($context, $phpDoc);
            if ($translation) {
                $replaces[$phpDoc] = $translation->getTranslation();
            } else {
                $translation = $poEntries->insert($context, $phpDoc);
                $translation->setTranslation($phpDoc);
                $i = 2;
                do {
                    $commentLine = trim($lines[$lineNum + $i], ' {');
                    $i++;
                } while (substr($commentLine, 0, 1) == '*');
                $translation->addComment($commentLine);
            }
        }

        //todo diff with rest of outdated (nonexistent anymore) entries
        $generated = $generator->generateFile($poEntries, $poFileName);
        if (!$generated) {
            throw new \RuntimeException("Generation of $poFileName failed");
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
        return getcwd() . '/../translations/';
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
     * @param $allTokens
     * @param $lines
     *
     * @return mixed
     */
    private function detectSourceNamespace($allTokens, $lines)
    {
        $nsLine = Arrays::from($allTokens)
                ->filter(
                    function ($elem) {
                        return $elem[0] == T_NAMESPACE;
                    }
                )
                ->first()
                ->obtain()[2] - 1;

        $namespaceDeclaration = $lines[$nsLine];
        preg_match('/namespace\s+(?P<ns>[a-z\_]+)/i', $namespaceDeclaration, $matches);

        return $matches['ns'];
    }

    /**
     * @param $poFileName
     *
     * @internal param $extractor
     * @return Entries
     */
    private function setupPoEntries($poFileName)
    {
        $extractor = new Po();
        try {
            $poEntries = $extractor->extract($poFileName);

            if ($poEntries === false) {
                throw new \Exception;
            }

        } catch (\Exception $e) {
            $poEntries = new Entries();
        }

        return $poEntries;
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
}
