morse-php
=========

PHP morse code utilities.  
Based on [rexxars/morse-php](https://github.com/rexxars/morse-php)  
Code table from [Morse_code_mnemonics](https://en.wikipedia.org/wiki/Morse_code_mnemonics)

Support case sensitive **setCaseSense(true)** in 2 way:  
setUpperCaseMod(false): lowerCaseModifer `&` (Interval) - added before any chars in lower case  
setUpperCaseMod(true): upperCaseModifer `+` (End of message) - added before any chars in upper case

[![CI](https://github.com/skillcoder/morse-php/actions/workflows/ci.yml/badge.svg)](https://github.com/skillcoder/morse-php/actions/workflows/ci.yml)

# Requirements

- PHP 8.5 or newer, with the `mbstring` extension
- Version 1.0.x supports PHP 5.6 – 7.3

# Usage

``` php
<?php
// Translate from/to morse:
$text = new Morse\Text();
$morse = $text->toMorse('SOS');

echo $morse; // ... --- ...
echo $text->fromMorse($morse); // SOS

// Generate a WAV-file:
$wav = new Morse\Wav();
file_put_contents('sos.wav', $wav->generate('SOS'));
```

~~~php
<?php
namespace Morse;
require_once('vendor/autoload.php');
$signal = '___ ._... _._  ._... __ ._... ._ ._... _.';
$table = new Table('_');
$text = new Text($table);
$text->setCaseSense(true);
$text->setUpperCaseMod(false);
echo $text->fromMorse($signal); // Ok man
~~~

# Installing

To include `morse-php` in your project, add it to your `composer.json` file:

```json
{
    "require": {
        "skillcoder/morse": "^1.1.0"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "skillcoder/morse",
                "version": "1.1.0",
                "source": {
                    "url": "https://github.com/skillcoder/morse-php.git",
                    "type": "git",
                    "reference": "master"
                },
                "autoload": {
                    "classmap": [""]
                }
            }
        }
    ]
}
```

# License

MIT licensed. See LICENSE for full terms.
