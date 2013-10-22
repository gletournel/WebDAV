<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav\Header;

/**
 * @covers Grale\WebDav\Header\TimeoutHeader
 */
class TimeoutHeaderTest extends \PHPUnit_Framework_TestCase
{
    public function testTimeout()
    {
        $timeout = new TimeoutHeader('3600');
        $this->assertEquals(3600, $timeout->getSeconds());
    }

    public function testInfiniteTimeout()
    {
        $timeout = TimeoutHeader::getInfinite();
        $this->assertEquals(TimeoutHeader::INFINITE, $timeout->getSeconds());
    }

    public function testStringRepresentation()
    {
        $timeout = new TimeoutHeader(86400);
        $this->assertEquals('Second-86400', (string)$timeout);
    }

    public function testInfiniteStringRepresentation()
    {
        $timeout = TimeoutHeader::getInfinite();
        $this->assertEquals('Infinite', (string)$timeout);
    }

    public function testValidity()
    {
        $now     = time();
        $timeout = new TimeoutHeader(3600);

        $this->assertEquals($now + 3600, $timeout->getValidity($now));
    }

    public function testInfiniteValidity()
    {
        $timeout = TimeoutHeader::getInfinite();
        $this->assertEquals(-1, $timeout->getValidity());
    }

    public function testParseInvalidTimeout()
    {
        $timeout = TimeoutHeader::parse('dummy');
        $this->assertEmpty($timeout);
    }

    /**
     * @dataProvider getTimeouts
     */
    public function testParse($str, $seconds)
    {
        $timeout = TimeoutHeader::parse($str);
        $this->assertInstanceOf('\Grale\WebDav\Header\TimeoutHeader', $timeout);
        $this->assertEquals($seconds, $timeout->getSeconds());
    }

    public function getTimeouts()
    {
        return array(
            array('Infinite', -1),
            array('infinite', -1),
            array('Second-3600', 3600),
            array('second-86400', 86400),
            array(1234567, 1234567),
            array('42', 42)
        );
    }
}
