# Translator

Used with [Sami](https://github.com/fabpot/Sami) tool to generate multi-language HTML docs from your PhpDocs.

Translation routines is powered up by [Gettext format](http://www.gnu.org/software/gettext/) and it's [php implementation](https://github.com/oscarotero/Gettext).

Basic usage is to prepare Sami config php file...

```php
// include all necessary dependencies
require_once __DIR__ . '/vendor/autoload.php';


use Sami\Sami;
use Symfony\Component\Finder\Finder;
use umi\sami\translator\TranslatorPlugin;

// create any files iterator you like
$iterator = Finder::create()
    ->in(getcwd())
    ->exclude('.git')
    ->exclude('.idea')
    ->exclude('vendor')
    ->exclude('tests')
    ->exclude('docs')
    ->name('*.php');

// tune up generation process
$options = [
    // where to look your php source code
    // specify version placeholder for separate HTML output
    'build_dir' => getcwd() . '/docs/build/%version%',
    'cache_dir' => getcwd() . '/docs/cache/%version%',
    // this is required option
    'default_opened_level' => 2,
];

// pass iterator & options to Sami tool instance
$sami = new Sami($iterator, $options);

// now power it up with i18n with direct instantiation
$sami[TranslatorPlugin::ID] = new TranslatorPlugin('ru', $sami, [
    // skip phpDocs containing stub docs
    'ignoreDocPatterns' => [
        '/@inheritdoc/',
        '/@copyright/'
    ],
    'translationsPath'=>'any/path/you/want/to/keep/gettext/files',
    // path also can be extended with custom version subdir
    // (by default version dir will be appended to translations path to avoid data losing)
    //'translationsPath'=>'D:/gettext-repo/%version%/subdir',

    // path can be relative to build dir, to keep translations together with API build
    //'translationsPath'=>'%build%/translations/', // add %version% anywhere, to your taste

    // whether to add PhpDoc'ed code as translation comment, for sensible human-translating
    'useContextComments'=> true
]);

return $sami;
```

...and then to execute Sami console tool passing this php config file location:

```
sami.php update /path/to/config.php
```

Now you have .ru.po-.pot file pairs created in `translationsPath`. Open your PoEdit tool and start i18'ning!

After you saved translation results and generated .mo files, you can run Sami again to refresh HTML build:

```
sami.php update /path/to/config.php
```