<?php
/**
 * UMI.Framework (http://umi-framework.ru/)
 *
 * @link      http://github.com/Umisoft/framework for the canonical source repository
 * @copyright Copyright (c) 2007-2013 Umisoft ltd. (http://umisoft.ru/)
 * @license   http://umi-framework.ru/license/bsd-3 BSD-3 License
 */
namespace velosipedist\sami\translator\extractors;

use Gettext\Entries;
use Gettext\Extractors\Extractor;
use Gettext\Translation;

/**
 * Class PhpdocExtractor
 */
class PhpdocExtractor extends Extractor
{
    public static $useCommentedCodeAsEntriesComments = true;
    public static $ignoreDocPatterns = [];
    private static $phpDocs;

    static public function parse($file, Entries $entries)
    {
        $fileContents = file_get_contents($file);

        $allTokens = token_get_all($fileContents);

        $lines = self::toLines($fileContents);

        $phpDocs = [];
        foreach ($allTokens as $tok) {
            if ($tok[0] == T_DOC_COMMENT) {
                $phpDocs[$tok[2]] = $tok[1];
            }
        }
        $ignoreDocPatterns = static::$ignoreDocPatterns;
        $phpDocs = array_filter(
            $phpDocs,
            function ($doc) use ($ignoreDocPatterns) {
                foreach ($ignoreDocPatterns as $pattern) {
                    if (preg_match($pattern, $doc)) {
                        return false;
                    }
                }
                return true;
            }
        );

        self::$phpDocs = $phpDocs;

        foreach ($phpDocs as $lineNum => $phpDoc) {
            /** @var $translation Translation */
            $translation = $entries->insert(null, $phpDoc);
            $translation->setTranslation($phpDoc);

            if (self::$useCommentedCodeAsEntriesComments) {
                $i = 2;
                do {
                    $commentLine = trim($lines[$lineNum + $i], ' {');
                    $i++;
                } while (preg_match('/^\s*\*/', $commentLine));
                $translation->addComment(trim($commentLine));
            }
        }
        return $entries;
    }

    /**
     * @param $string
     *
     * @return array
     */
    protected static function toLines($string)
    {
        return preg_split("/((\r?\n)|(\r\n?))/", $string);
    }

    /**
     * @return array
     */
    public static function getPhpDocs()
    {
        return self::$phpDocs;
    }

}
