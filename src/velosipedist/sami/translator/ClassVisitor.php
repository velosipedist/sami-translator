<?php
namespace velosipedist\sami\translator;

use Sami\Parser\ClassVisitorInterface;
use Sami\Reflection\ClassReflection;
use Sami\Reflection\MethodReflection;
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
     *
     * @param ClassReflection $class
     *
     * @return array [msgid=>[msgstr, Reflection]]
     */
    private function collectMessages(ClassReflection $class)
    {
        $messages = [
            $this->classKey($class) => [$class->getDocComment(), $class],
        ];
        foreach ($class->getProperties() as $prop) {
            $messages[$this->propertyKey($prop)] = [$prop->getDocComment(), $prop];
        }
        foreach ($class->getMethods() as $meth) {
            $methodSignature = $this->methodSignature($meth);
            $methodKey = $this->methodKey($meth, $methodSignature);
            $messages[$methodKey] = [$meth->getDocComment(), $meth];
        }

        return $messages;
    }

    /**
     * @param $prop
     *
     * @return string
     */
    private function propertyKey($prop)
    {
        return $this->accessString($prop) . $prop->getHintAsString() . ' $' . $prop->getName();
    }

    /**
     * @param $meth
     * @param $methodSignature
     *
     * @return string
     */
    private function methodKey($meth, $methodSignature)
    {
        return $this->accessString($meth) . 'function ' . $meth->getName() . "($methodSignature)";
    }

    /**
     * @param PropertyReflection|MethodReflection $reflection
     *
     * @return string
     */
    private function accessString($reflection)
    {
        $ret = '';
        $modifiers = (int) $reflection->toArray()['modifiers'];
        $modifiersMap = [
            ClassReflection::MODIFIER_ABSTRACT  => 'abstract',
            ClassReflection::MODIFIER_FINAL     => 'final',
            ClassReflection::MODIFIER_PRIVATE   => 'private',
            ClassReflection::MODIFIER_PROTECTED => 'protected',
            ClassReflection::MODIFIER_PUBLIC    => 'public',
            ClassReflection::MODIFIER_STATIC    => 'static',
        ];
        foreach ($modifiersMap as $visibility => $modifierName) {
            if (($modifiers & $visibility) > 0) {
                $ret .= $modifierName . ' ';
            }
        }

        return $ret;
    }

    private function methodSignature(MethodReflection $meth)
    {
        return Arrays::from($meth->getParameters())
            ->each(
                function (ParameterReflection $param) {
                    return $param->getHintAsString() . ' $' . $param->getName();
                }
            )
            ->implode(', ')
            ->obtain();
    }

    /**
     * @param ClassReflection $class
     *
     * @return string
     */
    private function classKey(ClassReflection $class)
    {
        return 'class ' . $class->getShortName();
    }
}
