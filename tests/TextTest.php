<?php
namespace Morse;

/**
 * @author Espen Hovlandsdal <espen@hovlandsdal.com>
 */
class TextTest extends \PHPUnit\Framework\TestCase {
    public function testCanTranslateToMorse() {
        $this->assertSame(
            '. ... .--. . -.  .... --- ...- .-.. .- -. -.. ... -.. .- .-..',
            new Text()->toMorse('Espen Hovlandsdal')
        );
    }

    public function testInsertsInvalidCharacterReplacement() {
        $this->assertSame(
            '...- .- ..-. ..-. .-.. . .-.  . .-.  --. # -.. -',
            new Text()->toMorse('Vaffler er gødt')
        );
    }

    public function testCanUseAlternativeCharacterReplacement() {
        $text = new Text()->setInvalidCharacterReplacement('¤');
        $this->assertSame(
            '...- .- ..-. ..-. .-.. . .-.  . .-.  --. ¤ -.. -',
            $text->toMorse('Vaffler er gødt')
        );
    }

    public function testCanUseAlternativeWordSeparator() {
        $text = new Text()->setWordSeparator('¤');
        $this->assertSame(
            '...- .- ..-. ..-. .-.. . .-.¤. .-.¤--. --- -.. -',
            $text->toMorse('Vaffler er godt')
        );
    }

    public function testCanTranslateFromMorse() {
        $this->assertSame(
            'ESPEN HOVLANDSDAL',
            new Text()->fromMorse('. ... .--. . -.  .... --- ...- .-.. .- -. -.. ... -.. .- .-..')
        );
    }

    public function testHandlesInvalidCharacterReplacement() {
        $this->assertSame(
            'VAFFLER ER GDT',
            new Text()->fromMorse('...- .- ..-. ..-. .-.. . .-.  . .-.  --. # -.. -')
        );
    }

    public function testCanTranslateAlternativeCharacterReplacement() {
        $text = new Text()->setInvalidCharacterReplacement('¤');
        $this->assertSame(
            'VAFFLER ER GDT',
            $text->fromMorse('...- .- ..-. ..-. .-.. . .-.  . .-.  --. ¤ -.. -')
        );
    }

    public function testCanTranslateAlternativeWordSeparator() {
        $text = new Text()->setWordSeparator('¤');
        $this->assertSame(
            'VAFFLER ER GODT',
            $text->fromMorse('...- .- ..-. ..-. .-.. . .-.¤. .-.¤--. --- -.. -')
        );
    }

    public function testHandlesInvalidAtEndOfString() {
        $text = new Text()->setInvalidCharacterReplacement('¤');
        $this->assertSame(
            'AVSLAG',
            $text->fromMorse($text->toMorse('AVSLAG%'))
        );
    }

    public function testThrowsIfWordSeparatorIsEmpty() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIs('Word separator must not be empty');
        new Text()->setWordSeparator('');
    }

    public function testIgnoresTrailingCaseModifier() {
        $text = new Text();
        $text->setCaseSense(true);
        $this->assertSame('a', $text->fromMorse('.- .-.-.'));
    }

    public function testInvalidUtf8TranslatesToNothing() {
        $this->assertSame('', new Text()->toMorse("\xff"));
    }

    public function testCanPassCustomTable() {
        $table = new Table();
        $table['%'] = '001100110011';

        $text = new Text($table);
        $this->assertSame(
            'AVSLAG -30%',
            $text->fromMorse($text->toMorse('Avslag -30%'))
        );
    }

    public function testTextToSignal() {
        $str = '1F2hWApGiUUXs98DvOQ9XqVYfvaqQSU0mRSgOAn7t8SrfJ1cD';
        $expect = '.:::: ..:. ..::: .:... .... .:: .: .:... .::. ::. .:... .. ..: ..: :..: .:... ... ::::. :::.. :.. .:... ...: ::: ::.: ::::. :..: .:... ::.: ...: :.:: .:... ..:. .:... ...: .:... .: .:... ::.: ::.: ... ..: ::::: .:... :: .:. ... .:... ::. ::: .: .:... :. ::... .:... : :::.. ... .:... .:. .:... ..:. .::: .:::: .:... :.:. :..';
        $table = new Table(':');
        $text = new Text($table);
        $text->setCaseSense(true);
        $text->setUpperCaseMod(false);
        $signal = $text->toMorse($str);
        $this->assertSame($expect, $signal);
    }

    public function testSignalToText() {
        $signal = '.:::: ..:. ..::: .:... .... .:: .: .:... .::. ::. .:... .. ..: ..: :..: .:... ... ::::. :::.. :.. .:... ...: ::: ::.: ::::. :..: .:... ::.: ...: :.:: .:... ..:. .:... ...: .:... .: .:... ::.: ::.: ... ..: ::::: .:... :: .:. ... .:... ::. ::: .: .:... :. ::... .:... : :::.. ... .:... .:. .:... ..:. .::: .:::: .:... :.:. :..';
        $expect = '1F2hWApGiUUXs98DvOQ9XqVYfvaqQSU0mRSgOAn7t8SrfJ1cD';

        $table = new Table(':');
        $text = new Text($table);
        $text->setCaseSense(true);
        $text->setUpperCaseMod(false);
        $token = $text->fromMorse($signal);
        $this->assertSame($expect, $token);
    }

    public function testUpperSignalToText() {
        $signal = '.:::: .:.:. ..:. ..::: .... .:.:. .:: .:.:. .: .::. .:.:. ::. .. .:.:. ..: .:.:. ..: .:.:. :..: ... ::::. :::.. .:.:. :.. ...: .:.:. ::: .:.:. ::.: ::::. .:.:. :..: ::.: .:.:. ...: .:.:. :.:: ..:. ...: .: ::.: .:.:. ::.: .:.:. ... .:.:. ..: ::::: :: .:.:. .:. .:.:. ... ::. .:.:. ::: .:.:. .: :. ::... : :::.. .:.:. ... .:. ..:. .:.:. .::: .:::: :.:. .:.:. :..';
        $expect = '1F2hWApGiUUXs98DvOQ9XqVYfvaqQSU0mRSgOAn7t8SrfJ1cD';

        $table = new Table(':');
        $text = new Text($table);
        $text->setCaseSense(true);
        $token = $text->fromMorse($signal);
        $this->assertSame($expect, $token);
    }

    public function testUpperTextToSignal() {
        $str = '1F2hWApGiUUXs98DvOQ9XqVYfvaqQSU0mRSgOAn7t8SrfJ1cD';
        $expect = '.:::: .:.:. ..:. ..::: .... .:.:. .:: .:.:. .: .::. .:.:. ::. .. .:.:. ..: .:.:. ..: .:.:. :..: ... ::::. :::.. .:.:. :.. ...: .:.:. ::: .:.:. ::.: ::::. .:.:. :..: ::.: .:.:. ...: .:.:. :.:: ..:. ...: .: ::.: .:.:. ::.: .:.:. ... .:.:. ..: ::::: :: .:.:. .:. .:.:. ... ::. .:.:. ::: .:.:. .: :. ::... : :::.. .:.:. ... .:. ..:. .:.:. .::: .:::: :.:. .:.:. :..';
        $table = new Table(':');
        $text = new Text($table);
        $text->setCaseSense(true);
        $signal = $text->toMorse($str);
        $this->assertSame($expect, $signal);
    }

}
