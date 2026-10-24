<?php
// Planted issues: float operands for % (8.1), chr() outside 0-255 (8.5), division by zero (8.0).

class Bytes
{
    public static function littleEndian32($value)
    {
        $out = '';
        $x = $value;
        for ($i = 0; $i < 4; $i++) {
            $out .= chr($x % 256);
            $x = floor($x / 256);
        }
        return $out;
    }

    public static function byte($n)
    {
        return chr($n);
    }

    public static function lowByteOfRate($rate)
    {
        return $rate % 256;
    }

    public static function perSecond($bytes, $seconds)
    {
        return $bytes / $seconds;
    }
}
