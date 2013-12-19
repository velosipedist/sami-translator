<?php
/**
 * UMI.Framework (http://umi-framework.ru/)
 *
 * @link      http://github.com/Umisoft/framework for the canonical source repository
 * @copyright Copyright (c) 2007-2013 Umisoft ltd. (http://umisoft.ru/)
 * @license   http://umi-framework.ru/license/bsd-3 BSD-3 License
 */

namespace umi\sami\console\command;

use Gettext\Entries;
use Gettext\Extractors\Po;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Underscore\Types\String;

/**
 * Prepares src classes docs translations
 */
class PreTranslate extends Command
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('pre-translate');
        $this->addArgument(
            'path', InputArgument::REQUIRED, 'Path to source files'
        )->addOption(
            'translations', 't', InputOption::VALUE_REQUIRED, 'Path to translations files dir'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        require_once $path.'/vendor/autoload.php';

        $iterator = $this->iterateSources($path);
        $extractor = new Po();
        $generator = new \Gettext\Generators\Po();
        $translationsPath = $input->hasOption('translations')
            ? $input->getOption('translations')
            : realpath($path.'/../translations');

        $output->writeln('Translating <info>'.$path.'</info>');
        $entries = $extractor->extract($translationsPath);

        if($entries === false){
            $entries = new Entries();
        }

        foreach ($iterator as $file) {
//            $classname = String::from(dirname($file))
//                ->remove($path)
//                ->sliceTo('.php')
//                ->replace('/', '\\')
//                ->obtain();
            try {
//                $class = new \ReflectionClass($classname);
                $docs = $this->parseDocsFromFile($file->getPath());
                foreach ($docs as $doc) {
                    if(!$entries->find(null, $doc)){
                        $entries->insert(null, $doc);
                    }
                }

            } catch (Exception $e) {
                $output->writeln('Reflection err: <error>'.$e->getMessage().'</error>');
                continue;
            }
            $copyName = String::from(dirname($file))->replace($path, $translationsPath);
        }
        $generator->generateFile($entries, $translationsPath);

    }

    /**
     * @param $path
     *
     * @return \Symfony\Component\Finder\Finder
     */
    protected function iterateSources($path)
    {
        return Finder::create()
            ->in($path)
            ->exclude('.git')
            ->exclude('.idea')
            ->exclude('vendor')
            ->exclude('tests')
            ->name('*.php');
    }

    private function fillEntriesFromClass(Entries $entries, \ReflectionClass $class)
    {
        $className = $class->getName();
        $entries->insert(null, $className, $class->getDocComment());
        foreach ($class->getMethods() as $method) {
            $entries->insert(null, $className.'::'.$method->getName(), $method->getDocComment());
        }
        foreach ($class->getProperties() as $prop) {
            $entries->insert(null, $className.'@'.$prop->getName(), $prop->getDocComment());
        }
    }

    private function parseDocsFromFile($path)
    {
        $ret = [];
        $tokens = token_get_all(file_get_contents($path), T_DOC_COMMENT);
        foreach ($tokens as $doc) {
            //todo ignore @inheritdoc
            $entries->insert(null,$doc);
        }

        return $ret;
    }

}
