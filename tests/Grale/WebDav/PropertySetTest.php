<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav;

class PropertySetTest extends \PHPUnit_Framework_TestCase
{
    protected static $properties = array(
        'R:bigbox',
        'D:creationdate',
        'D:displayname',
        'D:getcontentlanguage',
        'D:getcontentlength',
        'D:getcontenttype'
    );

    protected $props;

    public function setUp()
    {
        $this->props = new PropertySet(self::$properties);
    }

    /** Testing IteratorAggregate **/

    public function testIterator()
    {
        $compare = array();

        foreach ($this->props as $fullname => $value) {
            $compare[] = $fullname;
        }

        $this->assertEquals(self::$properties, $compare);
    }

    /** Testing ArrayAccess **/

    public function testOffsetSetAndGet()
    {
        $etag = md5('content');
        $this->props['D:getetag'] = $etag;
        $this->assertEquals($etag, $this->props['D:getetag']);
    }

    public function testOffsetExists()
    {
        $this->assertTrue(isset($this->props['R:bigbox']), 'Unable to assert that R:bigbox property exists!');
    }

    public function testOffsetUnset()
    {
        unset($this->props['R:bigbox']);
        $this->assertFalse(isset($this->props['R:bigbox']), 'Unable to assert that R:bigbox property has been deleted!');
    }

    /** Testing Countable **/

    public function testCount()
    {
        $this->assertEquals(count(self::$properties), $this->props->count());
    }
}
