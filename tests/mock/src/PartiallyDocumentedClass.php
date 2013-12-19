<?php
/**
 * UMI.Framework (http://umi-framework.ru/)
 *
 * @link      http://github.com/Umisoft/framework for the canonical source repository
 * @copyright Copyright (c) 2007-2013 Umisoft ltd. (http://umisoft.ru/)
 * @license   http://umi-framework.ru/license/bsd-3 BSD-3 License
 */
namespace mock;

/**
 * Class CompleteDocumentedClass
 */
class PartiallyDocumentedClass
{
    public function publicFoo()
    {

    }

    protected function protectedFoo()
    {

    }

    /**
     * This is publicFooWithParams
     *
     * @param $foo This is foo
     * @param $bar This is bar
     *
     * @return boolean Returns boolean
     */
    public function publicFooWithParams($foo, $bar)
    {
        return true;
    }
}
 