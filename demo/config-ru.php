<?php
require_once __DIR__ . '/vendor/autoload.php';

use Sami\Sami;
use Symfony\Component\Finder\Finder;
use velosipedist\sami\translator\TranslatorPlugin;

$options = [
    'build_dir' => getcwd() . '/docs/build/%version%',
    'cache_dir' => getcwd() . '/docs/cache/%version%',
    'default_opened_level' => 2,
    'force' => true,
];

$iterator = Finder::create()
    ->in(getcwd())
    ->exclude('.git')
    ->exclude('.idea')
    ->exclude('vendor')
    ->exclude('tests')
    ->exclude('docs')
    ->name('*.php');

$sami = new Sami($iterator, $options);

$lang = 'ru'; //todo pass from args
//todo make use of Pimple::extend()
$sami[TranslatorPlugin::ID] = new TranslatorPlugin($lang, $sami, [
    'ignoreDocPatterns' => [
        '/@inheritdoc/',
        '/@copyright/'
    ]
]);

return $sami;
