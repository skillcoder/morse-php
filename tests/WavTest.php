<?php
namespace Morse;

/**
 * @author Espen Hovlandsdal <espen@hovlandsdal.com>
 */
class WavTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        if (!extension_loaded('fileinfo')) {
            $this->markTestSkipped('The finfo extension is not available');
        }
    }

    public function testCanGenerateValidWav() {
        $wav = new Wav();
        $output = $wav->generate('Espen');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($output);

        $this->assertSame('audio/x-wav', $mime);
    }

    public function testCanSetCwSpeed() {
        $wav = new Wav()->setCwSpeed(10);
        $output = $wav->generate('Espen');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($output);

        $this->assertSame('audio/x-wav', $mime);
    }

    public function testThrowsIfInvalidCwSpeedSet() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains('Speed must be numeric');
        new Wav()->setCwSpeed('foo');
    }

    public function testCanSetSampleRate() {
        $wav = new Wav()->setSampleRate(8000);
        $output = $wav->generate('Espen');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($output);

        $this->assertSame('audio/x-wav', $mime);
    }

    public function testThrowsIfInvalidSampleRateSet() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains('Sample rate must be numeric');
        new Wav()->setSampleRate('foo');
    }

    public function testCanSetFrequency() {
        $wav = new Wav()->setFrequency(8000);
        $output = $wav->generate('Espen');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($output);

        $this->assertSame('audio/x-wav', $mime);
    }

    public function testThrowsIfInvalidFrequencySet() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIsOrContains('Frequency must be numeric');
        new Wav()->setFrequency('foo');
    }

    public function testCanGenerateLongerValidWav() {
        $wav = new Wav();
        $output = $wav->setFrequency(100)->generate($this->getLoremIpsum());

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($output);

        $this->assertSame('audio/x-wav', $mime);
    }

    public function testThrowsIfCwSpeedIsZero() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIs('Speed must be greater than zero');
        new Wav()->setCwSpeed(0);
    }

    public function testThrowsIfSampleRateIsZero() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIs('Sample rate must be greater than zero');
        new Wav()->setSampleRate(0);
    }

    public function testThrowsIfFrequencyIsZero() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageIs('Frequency must be greater than zero');
        new Wav()->setFrequency(0);
    }

    public function testFractionalSampleRateIsWrittenAsInteger() {
        $output = new Wav()->setSampleRate(8000.5)->generate('E');

        // fmt chunk: sample rate at byte 24, byte rate at byte 28 (8-bit mono)
        $this->assertSame(pack('V', 8000), substr($output, 24, 4));
        $this->assertSame(pack('V', 8000), substr($output, 28, 4));
    }

    protected function getLoremIpsum() {
        return (
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ' .
            'Duis sed dignissim arcu. Etiam non euismod nulla. ' .
            'Cras non sagittis velit. Donec et imperdiet ipsum. ' .
            'Fusce vel enim ut neque pellentesque congue. ' .
            'Nunc posuere vitae justo eu dignissim. ' .
            'Sed eget nunc sed massa auctor posuere. ' .
            'Etiam condimentum ullamcorper tellus, et tempor nisl aliquet a.' .
            'Nulla finibus ut nulla eu rhoncus.'
        );
    }
}
