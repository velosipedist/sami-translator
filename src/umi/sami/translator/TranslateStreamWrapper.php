<?php
namespace umi\sami\translator;

/**
 * Class TranslateStreamWrapper
 */
class TranslateStreamWrapper
{
    /**
     * @var TranslatorPlugin $translator
     */
    private static $translator;
    /**
     * @var string $currentFile
     */
    private $currentFile;
    /**
     * @var int $counter
     */
    private $position = 0;
    /**
     * @var string $contents
     */
    private $contents;
    /**
     * @var int $length
     */
    private $length;

    function __construct()
    {
        if (function_exists('\mb_internal_encoding')) {
            defined('MB_ENCODING_SUPPORTED') or define('MB_ENCODING_SUPPORTED', true);
            defined('MB_INTERNAL_ENCODING') or define('MB_INTERNAL_ENCODING', mb_internal_encoding());
        } else {
            defined('MB_ENCODING_SUPPORTED') or define('MB_ENCODING_SUPPORTED', false);
            defined('MB_INTERNAL_ENCODING') or define('MB_INTERNAL_ENCODING', false);
        }
        defined('MB_LATIN_1') or define('MB_LATIN_1', 'ISO-8859-1');
    }

    /**
     * @param TranslatorPlugin $translator
     */
    public static function setupTranslatorPlugin($translator)
    {
        self::$translator = $translator;
        if (!in_array("doclocal", stream_get_wrappers())) {
            stream_wrapper_register('doclocal', __CLASS__);
        }
    }

    /**
     * Receives doclocal://*** path for creating translated file «on the fly»
     *
     * @param $path
     * @param $mode
     * @param $options
     * @param $opened_path
     *
     * @return bool
     */
    function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->currentFile = $path;
        $this->position = 0;
        $this->contents = self::$translator->parseDocsFromFile($this->extractRealFileName($this->currentFile));
        $this->length = $this->bytes($this->contents, 'utf-8');

        return true;
    }

    /**
     * Transform «obfuscated» file path to real
     *
     * @param string $file
     *
     * @return string
     */
    public function extractRealFileName($file)
    {
        return substr($file, strlen(TranslatorPlugin::PROTOCOL . '://'));
    }

    function stream_read($count)
    {
        $range = $this->bytesRange($this->contents, $this->position, $count, 'utf-8');
        $this->position += $count;

        return $range;
    }

    public function stream_write($data)
    {
        return 0;
    }

    public function stream_tell()
    {
        return $this->position;
    }

    function stream_metadata($path, $option, $var)
    {
        return true;
    }

    public function url_stat($path, $flags)
    {
        return [];
    }

    public function stream_stat()
    {
        return [];
    }

    public function stream_flush()
    {
        return true;
    }

    public function stream_close()
    {
    }

    public function stream_eof()
    {
        return $this->position >= $this->bytes($this->contents);
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        switch ($whence) {
            case SEEK_SET:
            {
                if ($this->isValidOffset($offset)) {
                    $this->position = $offset;

                    return true;
                } else {
                    return false;
                }
            }

            case SEEK_CUR:
            {
                if ($offset >= 0) {
                    $this->position += $offset;

                    return true;
                } else {
                    return false;
                }
            }

            case SEEK_END:
            {
                if ($this->isValidOffset($this->position + $offset)) {
                    $this->position = $this->length + $offset;

                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    private function bytes($string)
    {
        if (!MB_ENCODING_SUPPORTED || MB_INTERNAL_ENCODING === MB_LATIN_1) {
            return strlen($string);
        }
        mb_internal_encoding(MB_LATIN_1);
        $count = strlen($string);
        mb_internal_encoding(MB_INTERNAL_ENCODING);

        return $count;
    }

    private function bytesRange($string, $start, $length = null)
    {
        if (!MB_ENCODING_SUPPORTED || MB_INTERNAL_ENCODING === MB_LATIN_1) {
            if (is_null($length)) {
                return substr($string, $start);
            } else {
                return substr($string, $start, $length);
            }
        }

        mb_internal_encoding(MB_LATIN_1);
        if (is_null($length)) {
            $result = substr($string, $start);
        } else {
            $result = substr($string, $start, $length);
        }
        mb_internal_encoding(MB_INTERNAL_ENCODING);

        return $result;
    }

    protected function isValidOffset($offset)
    {
        return ($offset >= 0) && ($offset < $this->length);
    }
}
