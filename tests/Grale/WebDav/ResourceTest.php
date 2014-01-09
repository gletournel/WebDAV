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

use Grale\WebDav\Property\DateTimeProperty;
use Grale\WebDav\Property\ResourceType;
use Grale\WebDav\Property\SupportedLock;
use Grale\WebDav\Property\LockDiscovery;

/**
 * @covers Grale\WebDav\Resource
 */
class ResourceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Grale\WebDav\Resource
     */
    protected $collection;

    /**
     * @var \Grale\WebDav\Resource
     */
    protected $resource;

    /**
     * @var Lock
     */
    protected $lock;

    public function setUp()
    {
        $properties = new PropertySet();
        $properties['D:displayname'] = 'Example collection';

        $properties->add(new DateTimeProperty('D:creationdate', '1997-12-01T17:42:21-08:00'));
        $properties->add(new ResourceType('collection'));

        $lockCapabilities = new SupportedLock(
            array(
                'write' => array(
                    'exclusive',
                    'shared'
                )
            )
        );

        $properties->add($lockCapabilities);

        $this->collection = new Resource('http://www.foo.bar/container/', $properties);

        $resourceProps = new PropertySet();
        $resourceProps['D:displayname'] = 'Example HTML resource';
        $resourceProps['D:getcontentlength'] = 4525;
        $resourceProps['D:getcontenttype'] = 'text/html';
        $resourceProps['D:getcontentlanguage'] = 'en';
        $resourceProps['D:getetag'] = 'zzyzx';

        $resourceProps->add(new DateTimeProperty('D:creationdate', '1997-12-01T18:27:21-08:00'));
        $resourceProps->add(new DateTimeProperty('D:getlastmodified', 'Monday, 12-Jan-98 09:25:56 GMT'));
        $resourceProps->add(new ResourceType());
        $resourceProps->add(
            new SupportedLock(array(
                'write' => array('exclusive', 'shared')
            ))
        );

        $this->lock = new Lock();
        $this->lock->setDepth(-1);
        $this->lock->setTimeout(604800);
        $this->lock->setOwner('http://www.ics.uci.edu/~ejw/contact.html');
        $this->lock->setToken('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4');

        $resourceProps->add(
            new LockDiscovery(array($this->lock))
        );

        $this->resource = new Resource('http://www.foo.bar/container/front.html', $resourceProps);
    }

    public function testHref()
    {
        $this->assertEquals('http://www.foo.bar/container/', $this->collection->getHref());
    }

    public function testType()
    {
        $this->assertEquals(array('collection'), $this->collection->getType());
    }

    public function testFilename()
    {
        $this->assertEquals(basename('http://www.foo.bar/container/'), $this->collection->getFilename());
    }

    public function testDisplayName()
    {
        $this->assertEquals('Example collection', $this->collection->getDisplayName());
    }

    public function testCollection()
    {
        $this->assertTrue($this->collection->isCollection());
    }

    public function testIsLockable()
    {
        $this->assertTrue($this->collection->isLockable());
    }

    public function testEtag()
    {
        $this->assertEquals('zzyzx', $this->resource->getEtag());
    }

    public function testContentType()
    {
        $this->assertEquals('text/html', $this->resource->getContentType());
    }

    public function testContentLanguage()
    {
        $this->assertEquals('en', $this->resource->getContentLanguage());
    }

    public function testContentLength()
    {
        $this->assertEquals(4525, $this->resource->getContentLength());
    }

    public function testCreationDate()
    {
        $this->assertEquals(new \DateTime('1997-12-01T18:27:21-08:00'), $this->resource->getCreationDate());
    }

    public function testLastModified()
    {
        $this->assertEquals(new \DateTime('Monday, 12-Jan-98 09:25:56 GMT'), $this->resource->getLastModified());
    }

    public function testSupportedLocks()
    {
        $this->assertTrue($this->collection->isLockable('write', 'exclusive'));
        $this->assertTrue($this->collection->isLockable('write', 'shared'));
    }

    public function testHasLock()
    {
        $this->assertTrue($this->resource->hasLock());
        $this->assertTrue($this->resource->hasLock('write'));
        $this->assertTrue($this->resource->hasLock('write', 'exclusive'));
    }

    public function testHasNoLock()
    {
        $this->assertFalse($this->collection->hasLock());
        $this->assertFalse($this->resource->hasLock('write', 'shared'));
    }

    public function testHasLockToken()
    {
        $this->assertTrue($this->resource->hasLockToken('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4'));
    }

    public function testGetLock()
    {
        $this->assertEquals(
            $this->lock,
            $this->resource->getLock('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4')
        );
    }

    public function testGetNoLocks()
    {
        $this->assertCount(0, $this->resource->getLocks('write', 'shared'));
    }

    public function testGetLocks()
    {
        $locks = $this->resource->getLocks('write', 'exclusive');

        $this->assertCount(1, $locks);
        $this->assertEquals(array($this->lock), $locks);
    }

    public function testCollectionStat()
    {
        $stats    = $this->collection->getStat();
        $mode     = 0040777;

        $this->assertEquals($mode, $stats[2], sprintf('Failed asserting that stat[2] equals to %o', $mode));
        $this->assertEquals(0, $stats[7], 'Failed asserting that stat[7] equals to 0 bytes');
        $this->assertEquals(0, $stats[8], 'Failed asserting that stat[8] equals to 0');
        $this->assertEquals(0, $stats[9], 'Failed asserting that stat[9] equals to 0');

        $this->assertEquals($mode, $stats['mode'], sprintf('Failed asserting that stat[mode] equals to %o', $mode));
        $this->assertEquals(0, $stats['size'], 'Failed asserting that stat[size] equals to 0 bytes');
        $this->assertEquals(0, $stats['atime'], 'Failed asserting that stat[atime] equals to 0');
        $this->assertEquals(0, $stats['mtime'], 'Failed asserting that stat[mtime] equals to 0');
    }

    public function testResourceStat()
    {
        $stats    = $this->resource->getStat();
        $created  = new \DateTime('1997-12-01T18:27:21-08:00');
        $modified = new \DateTime('Monday, 12-Jan-98 09:25:56 GMT');
        $ctime    = $created->getTimestamp();
        $atime    = $modified->getTimestamp();
        $mode     = 0100777;
        $bytes    = 4525;

        $this->assertEquals($mode, $stats[2], sprintf('Failed asserting that stat[2] equals to %o', $mode));
        $this->assertEquals($bytes, $stats[7], "Failed asserting that stat[7] equals to $bytes bytes");
        $this->assertEquals($ctime, $stats[10], "Failed asserting that stat[10] equals to $ctime");
        $this->assertEquals($atime, $stats[8], "Failed asserting that stat[8] equals to $atime");
        $this->assertEquals($atime, $stats[9], "Failed asserting that stat[9] equals to $atime");

        $this->assertEquals($mode, $stats['mode'], sprintf('Failed asserting that stat[mode] equals to %o', $mode));
        $this->assertEquals($bytes, $stats['size'], "Failed asserting that stat[size] equals to $bytes bytes");
        $this->assertEquals($ctime, $stats['ctime'], "Failed asserting that stat[ctime] equals to $ctime");
        $this->assertEquals($atime, $stats['atime'], "Failed asserting that stat[atime] equals to $atime");
        $this->assertEquals($atime, $stats['mtime'], "Failed asserting that stat[mtime] equals to $atime");
    }
}
