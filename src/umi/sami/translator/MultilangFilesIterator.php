<?php
namespace umi\sami\translator;

/**
 * Iterates the same sources as inner iterator,
 * but delegates source file pre-processing to localized source
 * todo rename class to something transparent
 */
class MultilangFilesIterator extends \IteratorIterator
{
    const ID = 'umi\sami\translator\Iterator';

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return 'doclocal://' . parent::current()
            ->getPathname();
    }
}
