<?php
require_once "vendor/autoload.php";
//require_once "src/umi/sami/translator/MultilangFilesIterator.php";
//require_once "src/umi/sami/translator/TranslateStreamWrapper.php";
//require_once "src/umi/sami/translator/TranslatorPlugin.php";
use Sami\Sami;
use Symfony\Component\Finder\Finder;
use umi\sami\translator\TranslatorPlugin;

$dirIterator = Finder::create()->in(__DIR__.'/tests/mock/src');

$options = [
    'build_dir'            => __DIR__ . '/../sami-test/build/%version%',
    'cache_dir'            => __DIR__ . '/../sami-test/cache/%version%',
    'default_opened_level' => 2,
    'force' => true,
];
if(isset($_ENV['sami.testdir'])){
    $options['build_dir'] = $_ENV['sami.testdir'].'/../build/%version%';
    $options['cache_dir'] = $_ENV['sami.testdir'].'/../cache/%version%';
}

$sami = new Sami($dirIterator, $options);

$lang = 'ru';//todo pass from args

$sami[TranslatorPlugin::ID] = new TranslatorPlugin($lang, $sami);

return $sami;