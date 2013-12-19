<?php
namespace umi\sami\translator;

/**
 * Iterates the same sources as inner iterator,
 * but delegates source file pre-processing to localized source
 */
class MultilangFilesIterator extends \IteratorIterator
{
    const ID = 'umi\sami\translator\Iterator';

    /**
     * @var TranslatorPlugin $translator
     */
    private $translator;

    public function __construct($iterator)
    {
        parent::__construct($iterator);

        $iterator
            ->exclude('.git')
            ->exclude('.idea')
            ->exclude('vendor')
            ->exclude('tests')
            ->name('*.php');
        if (!in_array("doclocal", stream_get_wrappers())) {
            stream_wrapper_register('doclocal','umi\sami\translator\TranslateStreamWrapper');
        }
    }


    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return 'doclocal://' . parent::current()->getPathname();
    }

    /**
     * @param \umi\sami\translator\TranslatorPlugin $translator
     */
    public function setTranslator($translator)
    {
        $this->translator = $translator;
        TranslateStreamWrapper::setTranslator($translator);
    }

    public function srcPath()
    {
        return $this->getInnerIterator();
    }
}
