<?php
namespace velosipedist\sami\translator;

/**
 * Class ParseException
 */
class ParseException extends \Exception
{
    const NAMESPACE_NOT_FOUND = 1;
    const NON_SOURCE_FILE = 2;
}
