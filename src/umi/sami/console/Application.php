<?php

/*
 * This file is part of the Sami utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace umi\sami\console;

use Symfony\Component\Console\Application as BaseApplication;
use umi\sami\console\command\PreTranslate;

class Application extends BaseApplication
{
    /**
     * Constructor.
     */
    public function __construct()
    {
//        error_reporting(-1);
//        ErrorHandler::register();

        parent::__construct('Umisoft Sami helper', 1.0);

        $this->add(new PreTranslate());
    }

    public function getLongVersion()
    {
        return parent::getLongVersion().' by <comment>Fabien Potencier</comment>';
    }
}
