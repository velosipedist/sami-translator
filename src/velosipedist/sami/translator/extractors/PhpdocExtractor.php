<?php
namespace velosipedist\sami\translator\extractors;

use Gettext\Entries;
use Gettext\Extractors\Extractor;
use Gettext\Translation;
use velosipedist\sami\translator\ClassVisitor;
use velosipedist\sami\translator\TranslatorPlugin;

/**
 * Class PhpdocExtractor
 */
class PhpdocExtractor extends Extractor
{
    public static $useCommentedCodeAsEntriesComments = true;
    public static $ignoreDocPatterns = [];
    private static $phpDocs;
    /** @var  TranslatorPlugin */
    private static $translator;

    static public function parse($file, Entries $entries)
    {
        $phpDocs = [];
        $reflection = self::$translator->getReflectionByFileName($file);

        $messages = ClassVisitor::groupDocsBySignatures($reflection, false);
        foreach ($messages as $msgid=>$msg) {
            $phpDocs[$msgid] = $msg[1]->getDocComment();
        }

        self::$phpDocs = $phpDocs;

        foreach ($phpDocs as $msgid => $phpDoc) {
            /** @var $translation Translation */
            $translation = $entries->insert(null, $msgid);
            $translation->setTranslation($phpDoc);

            if (self::$useCommentedCodeAsEntriesComments) {
//                $translation->addComment($phpDoc);
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

    /**
     * @param TranslatorPlugin $translator
     */
    public static function setTranslator(TranslatorPlugin $translator)
    {
        self::$translator = $translator;
    }

}
