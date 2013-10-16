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

use Grale\WebDav\Header\DepthHeader;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Exception\BadResponseException;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    ///////////////////////////////////////////
    /////////////// OPTIONS Method ////////////
    ///////////////////////////////////////////

    public function testGetCompliance()
    {
        $response = new Response(200, array(
            'Dav' => '1, 2, <http://apache.org/dav/propset/fs/1>'
        ));

        $client = new Client('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock($response));

        $result = $client->getCompliance();

        $this->assertEquals(array('1', '2', '<http://apache.org/dav/propset/fs/1>'), $result);
    }

    public function testGetAllowedMethods()
    {
        $response = new Response(200, array(
            'Allow' => 'GET, POST, MKCOL, PROPFIND'
        ));

        $client = new Client('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock($response));

        $result = $client->getAllowedMethods();

        $this->assertEquals(array('GET', 'POST', 'MKCOL', 'PROPFIND'), $result);
    }

    ///////////////////////////////////////////
    /////////////// HEAD Method ///////////////
    ///////////////////////////////////////////


    ///////////////////////////////////////////
    /////////////// GET Method ////////////////
    ///////////////////////////////////////////


    ///////////////////////////////////////////
    /////////////// PUT Method ////////////////
    ///////////////////////////////////////////


    ///////////////////////////////////////////
    /////////////// DELETE Method /////////////
    ///////////////////////////////////////////

    public function testDeleteSuccessfully()
    {
        $client = new Client('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(204)));

        $result = $client->delete('/container/');
        $status = $client->getLastResponseStatus();

        $this->assertTrue($result);
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
    }

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.6.2.1
     */
    public function testDeleteLockedResourceWithFailure()
    {
        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.delete-failed')));

        $result = $client->delete('/container/');
        $status = $client->getLastResponseStatus();

        $this->assertFalse($result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
    }

    ///////////////////////////////////////////
    /////////////// MKCOL Method //////////////
    ///////////////////////////////////////////

    public function testMkcol()
    {
        $client = new Client('http://www.server.org');
        $client->setHttpClient($this->getHttpClientMock(new Response(201)));

        $result = $client->mkcol('/webdisc/xfiles');
        $status = $client->getLastResponseStatus();

        $this->assertTrue($result, 'Failed asserting that the collection was created');
        $this->assertEquals(201, $status, 'Failed asserting that the status-code equals to 201 (Created)');
    }

    /**
     * @dataProvider getMkcolBadResponses
     *
     * @param int    $status  The expected HTTP status code
     * @param string $class   The expected exception class
     * @param string $message The expected exception message
     */
    public function testMkcolBadResponses($status, $class, $message)
    {
        $this->setExpectedException(__NAMESPACE__ . '\\' . $class, $message);

        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->mkcol('/resource');
    }

    public function getMkcolBadResponses()
    {
        return array(
            array(403, 'Exception\ClientFailureException', 'Forbidden'),
            array(405, 'Exception\ClientFailureException', 'Method Not Allowed'),
            array(409, 'Exception\ClientFailureException', 'Conflict'),
            array(415, 'Exception\ClientFailureException', 'Unsupported Media Type'),
            array(507, 'Exception\ServerFailureException', 'Insufficient Storage')
        );
    }

    ///////////////////////////////////////////
    /////////////// MOVE Method ///////////////
    ///////////////////////////////////////////

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.9.5
     */
    public function testMoveRegularFile()
    {
        $response = new Response(201, array('Location' => 'http://www.ics.uci.edu/users/f/fielding/index.html'));

        $client = new Client('http://www.ics.uci.edu');
        $client->setHttpClient($this->getHttpClientMock($response));

        $result  = $client->move('/~fielding/index.html', '/users/f/fielding/index.html');
        $status  = $client->getLastResponseStatus();
        $headers = $client->getLastResponseHeaders();
        $request = $client->getLastRequest();

        $this->assertTrue($result);
        $this->assertEquals(201, $status, 'Failed asserting that the status-code equals to 201 (Created)');
        $this->assertTrue(isset($headers['location']), 'Failed asserting that response contains the "location" header');
        $this->assertEquals('http://www.ics.uci.edu/users/f/fielding/index.html', $headers['location']);

        $this->assertContains('MOVE /~fielding/index.html HTTP/1.1', $request);
        $this->assertContains('Destination: http://www.ics.uci.edu/users/f/fielding/index.html', $request);
    }

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.9.6
     */
    public function testMoveLockedCollection()
    {
        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.move-locked-collection')));

        $result  = $client->move('/container/', '/othercontainer/', array(
            'recursive' => true,
            'overwrite' => false,
            'locktoken' => array(
                'opaquelocktoken:fe184f2e-6eec-41d0-c765-01adc56e6bb4',
                'opaquelocktoken:e454f3f3-acdc-452a-56c7-00a5c91e4b77'
            )
        ));
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertFalse($result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');

        $this->assertContains('MOVE /container/ HTTP/1.1', $request);
        $this->assertContains('Overwrite: F', $request);
        $this->assertContains('Destination: http://www.foo.bar/othercontainer/', $request);
        $this->assertContains(
            'If: (<opaquelocktoken:fe184f2e-6eec-41d0-c765-01adc56e6bb4>)' .
               ' (<opaquelocktoken:e454f3f3-acdc-452a-56c7-00a5c91e4b77>)', $request
        );
    }

    /**
     * @dataProvider getMoveBadResponses
     *
     * @param int    $status  The expected HTTP status code
     * @param string $class   The expected exception class
     * @param string $message The expected exception message
     */
    public function testMoveBadResponses($status, $class, $message)
    {
        $this->setExpectedException(__NAMESPACE__ . '\\' . $class, $message);

        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->move('/source', '/destination');
    }

    public function getMoveBadResponses()
    {
        return array(
            array(403, 'Exception\ClientFailureException', 'Forbidden'),
            array(409, 'Exception\ClientFailureException', 'Conflict'),
            array(412, 'Exception\ClientFailureException', 'Precondition Failed'),
            array(423, 'Exception\ClientFailureException', 'Locked'),
            array(502, 'Exception\ServerFailureException', 'Bad Gateway')
        );
    }

    ///////////////////////////////////////////
    /////////////// COPY Method ///////////////
    ///////////////////////////////////////////

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.8.6
     */
    public function testCopyWithOverwrite()
    {
        $client = new Client('http://www.ics.uci.edu');
        $client->setHttpClient($this->getHttpClientMock(new Response(204)));

        $result  = $client->copy('/~fielding/index.html', '/users/f/fielding/index.html');
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertTrue($result);
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
        $this->assertContains('COPY /~fielding/index.html HTTP/1.1', $request);
        $this->assertContains('Destination: http://www.ics.uci.edu/users/f/fielding/index.html', $request);
        $this->assertContains('Overwrite: T', $request);
        $this->assertContains('Depth: 0', $request);
    }

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.8.7
     */
    public function testCopyWithNoOverwrite()
    {
        $client = new Client('http://www.ics.uci.edu');
        $client->setHttpClient($this->getHttpClientMock(new Response(412)));

        $result  = $client->copy('/~fielding/index.html', '/users/f/fielding/index.html', array('overwrite' => false));
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertFalse($result);
        $this->assertEquals(412, $status, 'Failed asserting that the status-code equals to 412 (Precondition Failed)');
        $this->assertContains('COPY /~fielding/index.html HTTP/1.1', $request);
        $this->assertContains('Destination: http://www.ics.uci.edu/users/f/fielding/index.html', $request);
        $this->assertContains('Overwrite: F', $request);
        $this->assertContains('Depth: 0', $request);
    }

    /**
     * @link http://tools.ietf.org/html/rfc4918#section-9.8.8
     */
    public function testCopyCollection()
    {
        $client = new Client('http://www.example.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.copy-collection')));

        $result  = $client->copy('/container/', '/othercontainer/', array('recursive' => true));
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertFalse($result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
        $this->assertContains('COPY /container/ HTTP/1.1', $request);
        $this->assertContains('Destination: http://www.example.com/othercontainer/', $request);
        $this->assertContains('Overwrite: T', $request);
        $this->assertContains('Depth: Infinity', $request);
    }

    /**
     * @dataProvider getCopyBadResponses
     *
     * @param int    $status  The expected HTTP status code
     * @param string $class   The expected exception class
     * @param string $message The expected exception message
     */
    public function testCopyBadResponses($status, $class, $message)
    {
        $this->setExpectedException(__NAMESPACE__ . '\\' . $class, $message);

        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->copy('/container', '/othercontainer');
    }

    public function getCopyBadResponses()
    {
        return array(
            array(403, 'Exception\ClientFailureException', 'Forbidden'),
            array(409, 'Exception\ClientFailureException', 'Conflict'),
            array(412, 'Exception\ClientFailureException', 'Precondition Failed'),
            array(423, 'Exception\ClientFailureException', 'Locked'),
            array(502, 'Exception\ServerFailureException', 'Bad Gateway'),
            array(507, 'Exception\ServerFailureException', 'Insufficient Storage')
        );
    }

    ///////////////////////////////////////////
    /////////////// PROPFIND Method ///////////
    ///////////////////////////////////////////

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.1
     */
    public function testPropfindRetrievingNamedProperties()
    {
        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.propfind-named-props')));

        $client->xmlNamespaces['http://www.foo.bar/boxschema/'] = 'R';

        $properties = array(
            'R:bigbox',
            'R:author',
            'R:DingALing',
            'R:Random'
        );

        $result  = $client->propfind('/file', 0, $properties);
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $xml = '<D:propfind xmlns:D="DAV:">'
             .   '<D:prop xmlns:R="http://www.foo.bar/boxschema/">'
             .     '<R:bigbox/>'
             .     '<R:author/>'
             .     '<R:DingALing/>'
             .     '<R:Random/>'
             .   '</D:prop>'
             . '</D:propfind>';

        $this->assertContains('PROPFIND /file HTTP/1.1', $request);
        $this->assertContains('Content-Type: text/xml; charset="utf-8"', $request);
        $this->assertContains('Depth: 0', $request);
        $this->assertContains($xml, $request);

        $this->assertInstanceOf('Grale\\WebDav\\MultiStatus', $result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
    }

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.2
     */
    public function testPropfindUsingAllprop()
    {
        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.propfind-allprop')));

        $result  = $client->propfind('/container/', 1);
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $xml = '<D:propfind xmlns:D="DAV:">'
             .   '<D:allprop/>'
             . '</D:propfind>';

        $this->assertContains('PROPFIND /container/ HTTP/1.1', $request);
        $this->assertContains('Content-Type: text/xml; charset="utf-8"', $request);
        $this->assertContains('Depth: 1', $request);
        $this->assertContains($xml, $request);

        $this->assertInstanceOf('Grale\\WebDav\\MultiStatus', $result);
        $this->assertEquals(207, $status, 'Failed asserting that the status-code equals to 207 (Multi-Status)');
    }

    ///////////////////////////////////////////
    /////////////// LOCK Method ///////////////
    ///////////////////////////////////////////

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.8
     */
    public function testSimpleLockRequest()
    {
        $client = new Client('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.simple-lock')));

        $lock = $client->createLock('/workspace/webdav/proposal.doc', 'exclusive', array(
            'owner'   => 'http://www.ics.uci.edu/~ejw/contact.html',
            'timeout' => 4100000000
        ));

        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertInstanceOf('Grale\\WebDav\\Lock', $lock);
        $this->assertTrue($lock->isExclusive(), 'Failed asserting that created lock is an exclusive lock');

        $xml = '<D:lockinfo xmlns:D="DAV:">'
             .   '<D:lockscope><D:exclusive/></D:lockscope>'
             .   '<D:locktype><D:write/></D:locktype>'
             .   '<D:owner>'
             .     '<D:href>http://www.ics.uci.edu/~ejw/contact.html</D:href>'
             .   '</D:owner>'
             . '</D:lockinfo>';

        $this->assertContains($xml, $request);
        $this->assertContains('Depth: 0', $request);
        $this->assertContains('Timeout: Second-4100000000', $request);
        $this->assertContains('LOCK /workspace/webdav/proposal.doc HTTP/1.1', $request);

        $this->assertEquals('http://www.ics.uci.edu/~ejw/contact.html', $lock->getOwner());
        $this->assertEquals('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', $lock->getToken());
        $this->assertEquals(604800, $lock->getTimeout()->getSeconds());
        $this->assertEquals(DepthHeader::INFINITY, $lock->getDepth());
    }

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.9
     */
    public function testRefreshingWriteLock()
    {
        $client = new Client('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.refreshing-write-lock')));

        $result = $client->refreshLock(
            '/workspace/webdav/proposal.doc',
            'opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4',
            4100000000
        );
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertInstanceOf('Grale\\WebDav\\Lock', $result);
        $this->assertEquals('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', $result->getToken());
        $this->assertEquals(604800, $result->getTimeout()->getSeconds());
        $this->assertEquals(DepthHeader::INFINITY, $result->getDepth());

        $this->assertContains('Timeout: Second-4100000000', $request);
        $this->assertContains('LOCK /workspace/webdav/proposal.doc HTTP/1.1', $request);
        $this->assertContains('If: (<opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4>)', $request);
    }

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.10
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unexpected server response
     */
    public function testMultiResourceLockRequest()
    {
        $client = new Client('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock($this->getFixture('response.multi-resource-lock')));

        $client->createLock('/webdav/', 'exclusive', array(
            'owner'   => 'http://www.ics.uci.edu/~ejw/contact.html',
            'timeout' => 4100000000
        ));
    }

    /**
     * @dataProvider getLockBadResponses
     *
     * @param int    $status  The expected HTTP status code
     * @param string $class   The expected exception class
     * @param string $message The expected exception message
     */
    public function testLockBadResponses($status, $class, $message)
    {
        $this->setExpectedException(__NAMESPACE__ . '\\' . $class, $message);

        $client = new Client('http://www.foo.bar');
        $client->setHttpClient($this->getHttpClientMock(new Response($status)));
        $client->setThrowExceptions();
        $client->createLock('/resource', 'exclusive');
    }

    public function getLockBadResponses()
    {
        return array(
            array(412, 'Exception\ClientFailureException', 'Precondition Failed'),
            array(423, 'Exception\ClientFailureException', 'Locked')
        );
    }

    ///////////////////////////////////////////
    /////////////// UNLOCK Method /////////////
    ///////////////////////////////////////////

    /**
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.11.1
     */
    public function testUnlock()
    {
        $client = new Client('http://webdav.sb.aol.com');
        $client->setHttpClient($this->getHttpClientMock(new Response(204)));

        $result = $client->releaseLock(
            '/workspace/webdav/info.doc',
            'opaquelocktoken:a515cfa4-5da4-22e1-f5b5-00a0451e6bf7'
        );
        $status  = $client->getLastResponseStatus();
        $request = $client->getLastRequest();

        $this->assertTrue($result);
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
        $this->assertContains('UNLOCK /workspace/webdav/info.doc HTTP/1.1', $request);
        $this->assertContains('Lock-Token: <opaquelocktoken:a515cfa4-5da4-22e1-f5b5-00a0451e6bf7>', $request);
    }

    /** Mock objects and test fixtures **/

    /**
     * @param  string $name
     * @param  bool   $asString
     *
     * @return Request|Response
     */
    protected function getFixture($name, $asString = false)
    {
        $fixtures = realpath(__DIR__ . '/../../fixtures');
        $filename = "{$fixtures}/{$name}.txt";

        if (!file_exists($filename)) {
            throw new \RuntimeException('Could not load test fixture');
        }

        $contents = file_get_contents($filename);

        if (!$asString) {
            if (substr($name, 0, 7) == 'request') {
                $contents = RequestFactory::getInstance()->fromMessage($contents);
            } elseif (substr($name, 0, 8) == 'response') {
                $contents = Response::fromMessage($contents);
            }
        }

        return $contents;
    }

    /**
     * @param  \Guzzle\Http\Message\Response $response
     * @return \Guzzle\Http\Client
     */
    protected function getHttpClientMock(Response $response)
    {
        $client = $this->getMockBuilder('\Guzzle\Http\Client')
                       ->setMethods(array('send'))
                       ->getMock();

        if ($response->isError()) {
            $request = $this->getMockBuilder('\Guzzle\Http\Message\Request')
                            ->disableOriginalConstructor()
                            ->getMock();

            $e = BadResponseException::factory($request, $response);
            $client->expects($this->any())->method('send')->will($this->throwException($e));
        } else {
            $client->expects($this->any())->method('send')->will($this->returnValue($response));
        }

        return $client;
    }
}
