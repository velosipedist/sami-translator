<?php
namespace umi\sami\translator;

/**
 * Iterates the same sources as inner iterator,
 * but delegates source file pre-processing to localized source
 */
class MultilangFilesIterator extends \IteratorIterator
{
    const ID = 'umi\sami\translator\Iterator';

    public function __construct($iterator)
    {
        parent::__construct($iterator);

        $iterator
            ->exclude('.git')
            ->exclude('.idea')
            ->exclude('vendor')
            ->exclude('tests')
            ->name('*.php');
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return 'doclocal://' . parent::current()
            ->getPathname();
    }
}
