<?php
// Planted issues: PHPUnit 5 base class, untyped setUp().

class LegacyTest extends PHPUnit_Framework_TestCase
{
    private $old;

    protected function setUp()
    {
        $this->old = new OldStyle('espen');
    }

    public function testConstructorAndDynamicProperty()
    {
        $this->assertSame('espen', $this->old->name);
        $this->assertSame('ESPEN', $this->old->label());
        $this->assertSame('ESPEN:a|b', $this->old->label(array('a', 'b')));
    }

    public function testStrings()
    {
        $this->assertSame('e', $this->old->first());
        $this->assertSame('Hello espen', $this->old->greet());
    }

    public function testArrays()
    {
        $this->assertSame('a=1,b=2', $this->old->pairs(array('a' => 1, 'b' => 2)));
        $this->assertSame(array('a', 'b', 'c'), $this->old->sortWords(array('c', 'a', 'b')));
    }

    public function testBytes()
    {
        $this->assertSame("\xe8\x03\x00\x00", Bytes::littleEndian32(1000));
        $this->assertSame(chr(44), Bytes::byte(300));
        $this->assertSame(64, Bytes::lowByteOfRate(8000.5));
        $this->assertSame(5, Bytes::perSecond(10, 2));
    }

    public function testBag()
    {
        $bag = new Bag();
        $bag[] = 'first';
        $bag['key'] = 'value';
        $this->assertCount(2, $bag);
        $this->assertTrue(isset($bag['key']));
        $this->assertSame('value', $bag['key']);
    }
}
