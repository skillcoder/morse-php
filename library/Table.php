<?php
namespace Morse;

/**
 * Morse code table
 *
 * @author Espen Hovlandsdal <espen@hovlandsdal.com>
 * @copyright Copyright (c) Espen Hovlandsdal
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/rexxars/morse-php
 */
class Table implements \ArrayAccess {
    /**
     * An array of predefined codes
     *
     * @var array
     */
    private $predefinedCodes;

    /**
     * A reverse copy of the table (morse => character)
     *
     * @var array
     */
    private $reversedTable;

    /**
     * A table of predefined morse code mappings
     *
     * @var array
     */
    private $table = [
        'A' => '01',
        'B' => '1000',
        'C' => '1010',
        'D' => '100',
        'E' => '0',
        'F' => '0010',
        'G' => '110',
        'H' => '0000',
        'I' => '00',
        'J' => '0111',
        'K' => '101',    // Ready to Receive (Over)
        'L' => '0100',
        'M' => '11',
        'N' => '10',
        'O' => '111',
        'P' => '0110',
        'Q' => '1101',
        'R' => '010',    // Message Received
        'S' => '000',
        'T' => '1',
        'U' => '001',
        'V' => '0001',
        'W' => '011',
        'X' => '1001',
        'Y' => '1011',
        'Z' => '1100',

        '0' => '11111',
        '1' => '01111',
        '2' => '00111',
        '3' => '00011',
        '4' => '00001',
        '5' => '00000',
        '6' => '10000',
        '7' => '11000',
        '8' => '11100',
        '9' => '11110',

        // https://en.wikipedia.org/wiki/Morse_code_mnemonics
        // From: A contemporary Morse code chart: https://en.wikipedia.org/wiki/Morse_code_mnemonics#/media/File:Morse_Crib_Sheet.png
        // * - mark non standart symbol
        '.' => '010101', // Full stop
        ',' => '110011', // Comma
        '?' => '001100', // Interrogation mark
        "'" => '011110', // Apostrophe
        '!' => '101011', // 
        '/' => '10010',  // Fraction Bar (Division Sign)
        '(' => '10110',  // 
        ')' => '101101', // Brackets [()] (transmited before and after the word or words affected)
        '&' => '01000',  // Interval (Wait)
        ':' => '111000', // Colon
        ';' => '101010', // 
        '=' => '10001',  // Break || Double dash (=)
        '+' => '01010',  // * End of message
        '-' => '100001', // Hyphen || Dash
        '_' => '001101', // Underline (transmited before and after the word or words affected)
        '"' => '010010', // Quotation mark
        '$' => '0001001',
        '@' => '011010',
        '|' => '01001',  // * Separation Sign (between whole number and fraction)
        // '' => '00010',        // * Roger
        // '' => '10101',        // * Starting signal
        // '' => '000101',       // * Closing down (End ok)
        // chr(8) => '00000000', // * Erase || Error
        // 'SOS' => '000111000', // * Distress Call || SOS
    ];

    private $dash = '-';

    /**
     * Constructs a new instance of the table
     */
    public function __construct($dash_char = '-') {
        $this->dash = $dash_char;
        $this->predefinedCodes = array_keys($this->table);
        $this->reversedTable = array_flip($this->table);
    }

    /**
     * Returns whether the given offset (character) exists
     *
     * @param mixed $offset
     * @return boolean
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->table[$this->key($offset)]);
    }

    /**
     * Get the morse code for the given offset (character)
     *
     * @param mixed $offset
     * @return string|null null for an unknown character
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->table[$this->key($offset)] ?? null;
    }

    /**
     * Add a morse code mapping for the given offset (character)
     *
     * @param mixed $offset
     * @param string $value
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $offset = $this->key($offset);
        if ($this->offsetExists($offset)) {
            throw new \Exception('Can\'t override predefined character');
        } else if (!preg_match('#^[01]+$#', $value)) {
            throw new \Exception('Value must be a string of zeroes and ones (0/1)');
        } else if (isset($this->reversedTable[$value])) {
            throw new \Exception('There is already a character with value ' . $value);
        }

        $this->table[$offset] = $value;
        $this->reversedTable[$value] = $offset;
    }

    /**
     * Remove a morse code mapping for the given offset (character)
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void {
        $offset = $this->key($offset);
        if (in_array($offset, $this->predefinedCodes, true)) {
            throw new \Exception('Can\'t unset a predefined morse code');
        }

        unset($this->table[$offset]);
    }

    /**
     * `$table[] = ...` passes a null offset, which PHP stored under '' before null array
     * offsets were deprecated (PHP 8.5). Keep that behaviour explicitly.
     *
     * @param mixed $offset
     * @return mixed
     */
    private function key($offset) {
        return $offset ?? '';
    }

    /**
     * Get morse code (dit/dah) for a given character
     *
     * @param  string $offset
     * @return string
     */
    public function getMorse($character) {
        return strtr((string) $this->offsetGet($character), '01', '.'.$this->dash);
    }

    /**
     * Get character for given morse code
     *
     * @param  string $morse
     * @return string
     */
    public function getCharacter($morse) {
        $key = strtr($morse, '.'.$this->dash, '01');
        return $this->reversedTable[$key] ?? false;
    }
}
