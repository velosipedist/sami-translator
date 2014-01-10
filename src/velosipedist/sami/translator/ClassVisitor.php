<?php
namespace velosipedist\sami\translator;

use Sami\Parser\ClassVisitorInterface;
use Sami\Reflection\ClassReflection;
use Sami\Reflection\ConstantReflection;
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
    private $sami;

    /**
     * @param Sami $sami
     */
    public function __construct(Sami $sami)
    {
        if (is_null($this->sami)) {
            $this->sami = $sami;
        }
    }

    /**
     * @inheritdoc
     */
    function visit(ClassReflection $class)
    {
        /** @var $translator TranslatorPlugin */
        $translator = $this->sami[TranslatorPlugin::ID];
        $messages = $this->groupDocsBySignatures($class);
        $translator->translateClassReflection($class, $messages);

        return true;
    }

    /**
     * Build messages list indexed by unique signature
     * @param ClassReflection $class
     * @param bool $addInherits
     * @return array [msgid=>[msgstr, Reflection]]
     */
    public static function groupDocsBySignatures(ClassReflection $class, $addInherits = true)
    {
        $messages = [
            self::classKey($class) => [$class->getDocComment(), $class],
        ];
        foreach ($class->getConstants() as $const) {
            $messages[self::constKey($const)] = [$const->getDocComment(), $const];
        }
        foreach ($class->getProperties() as $prop) {
            $messages[self::propertyKey($prop)] = [$prop->getDocComment(), $prop];
        }
        foreach ($class->getMethods() as $meth) {
            if (strpos(strtolower(trim($meth->getDocComment())), '{@inheritdoc}') || !$meth->getDocComment()) {
                if(!$addInherits) {
                    continue;
                }
                $parentMeth = $class->getParentMethod($meth->getName());
                if ($parentMeth) {
                    /** @var $meth MethodReflection */
                    $meth->setDocComment($parentMeth->getDocComment());
                }
            }
            $methodSignature = self::methodSignature($meth);
            $messages[self::methodKey($meth, $methodSignature)] = [$meth->getDocComment(), $meth];
        }

        return $messages;
    }

    /**
     * @param PropertyReflection $prop
     * @return string
     */
    private static function propertyKey(PropertyReflection $prop)
    {
        return self::accessString($prop) . $prop->getHintAsString() . ' $' . $prop->getName();
    }

    /**
     * @param $meth
     * @param $methodSignature
     * @return string
     */
    private static function methodKey($meth, $methodSignature)
    {
        return self::accessString($meth) . 'function ' . $meth->getName() . "($methodSignature)";
    }

    /**
     * @param PropertyReflection|MethodReflection $reflection
     * @return string
     */
    private static function accessString($reflection)
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

    /**
     * @param MethodReflection $meth
     * @return mixed
     */
    private static function methodSignature(MethodReflection $meth)
    {
        /** @noinspection PhpParamsInspection */
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
     * @return string
     */
    private static function classKey(ClassReflection $class)
    {
        return 'class ' . $class->getShortName();
    }

    private static function constKey(ConstantReflection $const)
    {
        return 'const ' . $const->getName();
    }
}
