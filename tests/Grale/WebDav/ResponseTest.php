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

/**
 * @covers Grale\WebDav\Response
 */
class ResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Status is expected to be an integer or an array
     */
    public function testInvalidResponse()
    {
        new Response('http://foo.bar', new \StdClass());
    }

    public function testHrefStatusResponse()
    {
        $response = new Response('http://webdav.sb.aol.com/webdav/secret', 403);
        $this->assertEquals(Response::HREFSTATUS, $response->getType());
        $this->assertEquals('http://webdav.sb.aol.com/webdav/secret', $response->getHref());
        $this->assertEquals(403, $response->getStatus());
    }

    public function testPropStatusResponse()
    {
        $props = array(
            200 => array(
                new Property('D:displayname', 'Example resource'),
                new Property('R:author', 'J.J. Johnson')
            ),
            403 => array(
                new Property('R:DingALing'),
                new Property('R:Random')
            )
        );

        $response = new Response('http://www.foo.bar/file', $props, 'Example description');

        $this->assertEquals(Response::PROPSTATUS, $response->getType());
        $this->assertEquals(array('D:displayname', 'R:author'), $response->getPropertyNames());
        $this->assertEquals(array('R:DingALing', 'R:Random'), $response->getPropertyNames(403));
        $this->assertEquals('Example description', $response->getDescription());
        $this->assertEquals('http://www.foo.bar/file', $response->getHref());
        $this->assertCount(0, $response->getPropertyNames(404));
    }

    public function testResource()
    {
        $props = array(
            new Property('D:displayname', 'Example HTML resource'),
            new Property('D:getcontentlength', 4525),
            new DateTimeProperty('D:creationdate', '1997-12-01T18:27:21-08:00'),
            new DateTimeProperty('D:getlastmodified', 'Monday, 12-Jan-98 09:25:56 GMT'),
            new ResourceType()
        );

        $response = new Response('http://www.foo.bar/container/front.html', array(200 => $props));

        $this->assertTrue($response->hasResource());

        $resource = $response->getResource();

        $this->assertEquals('http://www.foo.bar/container/front.html', $resource->getHref());
    }
}
