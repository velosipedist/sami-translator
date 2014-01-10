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
 *
 * @property string $foo This is foo
 * @method  string $bar This is bar
 */
class CompleteDocumentedClass implements ImplementMe
{
    /**
     * Means exactly nothing
     */
    const FOO = 'FOO';
    /**
     * @var mixed $publicMember publicMemberDoc
     */
    public $publicMember;
    /**
     * @var mixed $protectedMember protectedMemberDoc
     */
    protected $protectedMember;
    /**
     * @var mixed $privateMember privateMemberDoc
     */
    private $privateMember;

    /**
     * @var mixed $publicStaticMember publicStaticMemberDoc
     */
    public static $publicStaticMember;
    /**
     * @var mixed $protectedStaticMember protectedStaticMemberDoc
     */
    protected static $protectedStaticMember;
    /**
     * @var mixed $privateStaticMember privateStaticMemberDoc
     */
    private static $privateStaticMember;

    /**
     * This is publicFoo
     */
    public function publicFoo()
    {

    }

    /**
     * This is protectedFoo
     */
    protected function protectedFoo()
    {

    }

    /**
     * @param mixed $foo This is foo
     * @param mixed $bar This is bar
     *
     * @return boolean Returns boolean
     */
    public function methodWithParams($foo, $bar)
    {
        return true;
    }

    /**
     * This is publicFooWithParamsAndComments
     *
     * @param mixed $foo This is foo
     * @param mixed $bar This is bar
     *
     * @return boolean Returns boolean
     */
    public function methodWithParamsAndComments($foo, $bar)
    {
        return true;
    }

    /**
     * @param mixed $foo
     * @param mixed $bar
     *
     * @return boolean
     */
    public function methodWithParamsWithoutComments($foo, $bar)
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function methodWithoutParamsWithoutComments()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function implementation()
    {
    }

    /**
     * This is code examples:
     * <code>function(){return "foo";}</code>
     *
     * There also has special case
     * <code>
     * protected function methodWithCodeInComment(){
     *     return true;
     * }
     * </code>
     *
     * Save this text
     *
     * @return boolean
     */
    protected function methodWithCodeInComment()
    {
        return true;
    }
}
