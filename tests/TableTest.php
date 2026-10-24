<?php
namespace Morse;

/**
 * @author Espen Hovlandsdal <espen@hovlandsdal.com>
 */
class TableTest extends \PHPUnit\Framework\TestCase {
    public function testCanGetAsIfArray() {
        $table = new Table();
        $this->assertSame('10001', $table['=']);
    }

    public function testCanUseIssetAsIfArray() {
        $table = new Table();
        $this->assertSame(true, isset($table['=']));
        $this->assertSame(false, isset($table['%']));
    }

    public function testCanSetNewCharacters() {
        $table = new Table();
        $table['%'] = '10000001';
        $this->assertSame('10000001', $table['%']);
    }

    public function testCantOverwritePredefinedCharacters() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains("Can't override predefined character");
        $table = new Table();
        $table['A'] = '101';
    }

    public function testCantUseNonMorseCharactersAsValueForCustomCharacter() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains('Value must be a string of zeroes and ones (0/1)');
        $table = new Table();
        $table['¤'] = '123';
    }

    public function testCantUseSameMorseCodeAsOtherCharacter() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains('There is already a character with value 1');
        $table = new Table();
        $table['¤'] = '1';
    }

    public function testCanUnsetPreviouslySetCustomCharacters() {
        $table = new Table();
        $table['%'] = '10000001';
        $this->assertSame(true, isset($table['%']));

        unset($table['%']);
        $this->assertSame(false, isset($table['%']));
    }

    public function testCantUnsetPredefinedCharacters() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains("Can't unset a predefined morse code");
        $table = new Table();
        unset($table['A']);
    }

    public function testCantUnsetPredefinedDigits() {
        // PHP turns the digit keys '0'-'9' into integers, so this must not depend on the key type.
        foreach (['5', 5] as $digit) {
            $table = new Table();
            $error = null;
            try {
                unset($table[$digit]);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
            $this->assertSame("Can't unset a predefined morse code", $error, 'unset ' . var_export($digit, true));
            $this->assertSame('00000', $table['5']);
        }
    }

    public function testAppendWithoutCharacterStoresUnderEmptyCharacter() {
        // `$table[] = ...` passes a null offset; PHP < 8.5 stored it under ''.
        $table = new Table();
        $table[] = '10000001';
        $this->assertSame('10000001', $table['']);
    }

    public function testUnknownCharacterReadsAsNull() {
        $table = new Table();
        $this->assertNull($table['%']);
        $this->assertSame('', $table->getMorse('%'));
    }
}
