<?php
/**
 * UMI.Framework (http://umi-framework.ru/)
 *
 * @link      http://github.com/Umisoft/framework for the canonical source repository
 * @copyright Copyright (c) 2007-2013 Umisoft ltd. (http://umisoft.ru/)
 * @license   http://umi-framework.ru/license/bsd-3 BSD-3 License
 */
namespace velosipedist\sami\translator;

use Sami\Parser\ClassVisitorInterface;
use Sami\Reflection\ClassReflection;
use Sami\Reflection\ParameterReflection;
use Sami\Reflection\PropertyReflection;
use Sami\Sami;
use Underscore\Types\Arrays;

/**
 * Class ClassVisitor
 */
class ClassVisitor implements ClassVisitorInterface
{
    private static $sami;

    /**
     * @param Sami $sami
     */
    public function __construct(Sami $sami)
    {
        if (is_null(self::$sami)) {
            self::$sami = $sami;
        }
    }

    /**
     * @inheritdoc
     */
    function visit(ClassReflection $class)
    {
        /** @var $translator TranslatorPlugin */
        $translator = self::$sami[TranslatorPlugin::ID];
        $messages = $this->collectMessages($class);
        $translator->translateClassReflection($class, $messages);
    }

    /**
     * Build messages list indexed by unique signature
     * @param ClassReflection $class
     *
     * @return array [msgid=>[msgstr, Reflection]]
     */
    private function collectMessages(ClassReflection $class)
    {
        $messages = [
            $class->getName() => [$class->getDocComment(), $class],
        ];
        foreach ($class->getProperties() as $prop) {
            /** @var $prop PropertyReflection */
            $messages['var ' . $prop->getName()] = [$prop->getDocComment(), $prop];
        }
        foreach ($class->getMethods() as $meth) {
            $methodSignature = Arrays::from($meth->getParameters())
                ->each(
                    function (ParameterReflection $param) {
                        return $param->getHintAsString() . ' $' . $param->getName();
                    }
                )
                ->implode(', ')
                ->obtain();
            $methodKey = 'function ' . $meth->getName() . "($methodSignature)";
            $messages[$methodKey] = [$meth->getDocComment(), $meth];
        }

        return $messages;
    }
}
