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
use Guzzle\Http\Client as HttpClient;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\RequestFactory;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    const BASEURL = 'http://www.foo.bar';

    protected $client;

    public function setUp()
    {
        $this->client = new Client(self::BASEURL);
    }

    public function getClient(HttpClient $httpClient = null)
    {
        $client = new Client(self::BASEURL);

        if ($httpClient !== null) {
            $client->setHttpClient($httpClient);
        }

        return $client;
    }

    public function testOptions()
    {
        $features = array('1', '2', '<http://apache.org/dav/propset/fs/1>');

        $response = new Response(200);
        $response->setHeader('Dav', implode(', ', $features));

        $this->client->setHttpClient(
            $this->getHttpClientMock($response, 'OPTIONS')
        );

        $this->assertEquals($features, $this->client->options());
    }

    public function testHead() {}
    public function testGet() {}
    public function testPut() {}





    public function testDelete()
    {
        $httpClient = $this->getHttpClientMock(new Response(200), 'delete');
        $result = $this->getClient($httpClient)->delete('/resource');

        $this->assertTrue($result, 'Failed asserting that the resource has been deleted');
    }

    /**
     * In this example the attempt to delete http://www.foo.bar/container/resource3 failed because it is locked, and no
     * lock token was submitted with the request. Consequently, the attempt to delete http://www.foo.bar/container/
     * also failed. Thus the client knows that the attempt to delete http://www.foo.bar/container/ must have also
     * failed since the parent can not be deleted unless its child has also been deleted. Even though a Depth header
     * has not been included, a depth of infinity is assumed because the method is on a collection.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.6.2.1
     */
    public function testDeleteLockedResource()
    {
        $request    = $this->getRequestTestFixtures('DELETE', 'A');
        $response   = $this->getResponseTestFixtures('DELETE', 'A');
        $httpClient = $this->getHttpClientMock($response, 'delete');

        $result = $this->getClient($httpClient)->delete('/container/');

        $this->assertFalse($result);
        //$this->assertEquals((string)$request, $client->getLastRequest());
        $this->assertEquals($response->getBody(), $client->getLastResponse());
    }

    public function testMkcol()
    {
        $httpClient = $this->getHttpClientMock(new Response(201), 'mkcol');

        $client = $this->getClient($httpClient);
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

        $response = new Response($status);
        $this->client->setHttpClient($this->getHttpClientMock($response, 'MKCOL'));
        $this->client->setThrowExceptions();
        $this->client->mkcol('/resource');
    }

    /**
     * This example shows resource http://www.ics.uci.edu/~fielding/index.html being moved to the location
     * http://www.ics.uci.edu/users/f/fielding/index.html. The contents of the destination resource would have
     * been overwritten if the destination resource had been non-null. In this case, since there was nothing
     * at the destination resource, the response code is 201 (Created).
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.9.5
     */
    public function testMoveRegularFile()
    {
        $response   = new Response(201, array('Location' => 'http://www.foo.bar/users/f/fielding/index.html'));
        $httpClient = $this->getHttpClientMock($response, 'move');

        $client  = $this->getClient($httpClient);
        $result  = $client->move('/~fielding/index.html', '/users/f/fielding/index.html');
        $status  = $client->getLastResponseStatus();
        $headers = $client->getLastResponseHeaders();

        $this->assertTrue($result, 'Failed asserting that the resource was moved');
        $this->assertEquals(201, $status, 'Failed asserting that the status-code equals to 201 (Created)');
        $this->assertTrue(isset($headers['location']), 'Failed asserting that response contains the "location" header');
        $this->assertEquals('http://www.foo.bar/users/f/fielding/index.html', $headers['location']);
    }

    /**
     * In this example the client has submitted a number of lock tokens with the request. A lock token will need
     * to be submitted for every resource, both source and destination, anywhere in the scope of the method, that
     * is locked. In this case the proper lock token was not submitted for the destination
     * http://www.foo.bar/othercontainer/C2/. This means that the resource /container/C2/ could not be moved.
     * Because there was an error copying /container/C2/, none of /container/C2's members were copied.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.9.6
     */
    public function testMoveCollection()
    {
        $request    = $this->getRequestTestFixtures('MOVE', 'A');
        $response   = $this->getResponseTestFixtures('MOVE', 'A');
        $httpClient = $this->getHttpClientMock($response, 'move');

        $client = $this->getClient($httpClient);
        $result = $client->move('/container/', '/othercontainer/');

        $this->assertInstanceOf('Grale\\WebDav\\MultiStatus', $result);
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

        $response = new Response($status);
        $this->client->setHttpClient($this->getHttpClientMock($response, 'MOVE'));
        $this->client->setThrowExceptions();
        $this->client->move('/source', '/destination');
    }

    /**
     * This example shows resource http://www.ics.uci.edu/~fielding/index.html being copied to the location
     * http://www.ics.uci.edu/users/f/fielding/index.html. The 204 (No Content) status code indicates the
     * existing resource at the destination was overwritten.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.8.6
     */
    public function testCopyWithOverwrite()
    {
        $httpClient = $this->getHttpClientMock(new Response(204), 'copy');

        $client = $this->getClient($httpClient);
        $result = $client->copy('/~fielding/index.html', '/users/f/fielding/index.html');
        $status = $client->getLastResponseStatus();

        $this->assertTrue($result, 'Failed asserting that the resource was copied');
        $this->assertEquals(204, $status, 'Failed asserting that the status-code equals to 204 (No Content)');
    }

    /**
     * The following example shows the same copy operation being performed, but with the Overwrite header
     * set to "F." A response of 412 (Precondition Failed) is returned because the destination resource has
     * a non-null state.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.8.7
     */
    public function testCopyWithNoOverwrite()
    {
        $httpClient = $this->getHttpClientMock(new Response(412), 'copy');

        $client = $this->getClient($httpClient);
        $result = $client->copy('/~fielding/index.html', '/users/f/fielding/index.html');
        $status = $client->getLastResponseStatus();

        $this->assertTrue($result, 'Failed asserting that the resource was copied');
        $this->assertEquals(412, $status, 'Failed asserting that the status-code equals to 412 (Precondition Failed)');
    }

    /**
     * @link http://tools.ietf.org/html/rfc4918#section-9.8.8
     */
    public function testCopyCollection()
    {
        /* COPY /container/ HTTP/1.1
         * Host: www.foo.bar
         * Destination: http://www.foo.bar/othercontainer/
         * Depth: infinity
         */
        $response = $this->getResponseTestFixtures('COPY', 'A');
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

        $response = new Response($status);
        $this->client->setHttpClient($this->getHttpClientMock($response, 'COPY'));
        $this->client->setThrowExceptions();
        $this->client->copy('/source', '/destination');
    }

    /**
     * Simple Lock Request
     *
     * This example shows the successful creation of an exclusive write lock on resource
     * http://webdav.sb.aol.com/workspace/webdav/proposal.doc. The resource http://www.ics.uci.edu/~ejw/contact.html
     * contains contact information for the owner of the lock. The server has an activity-based timeout policy in place
     * on this resource, which causes the lock to automatically be removed after 1 week (604800 seconds). Note that the
     * nonce, response, and opaque fields have not been calculated in the Authorization request header.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.8
     */
    public function testSimpleLockRequest()
    {
        $response = $this->getResponseTestFixtures('LOCK', 'A');

        $this->client->setHttpClient($this->getHttpClientMock($response, 'LOCK'));
        $lock = $this->client->createLock('/workspace/webdav/proposal.doc', 'exclusive', array(
            'owner' => 'http://www.ics.uci.edu/~ejw/contact.html'
        ));

        $this->assertInstanceOf('Grale\\WebDav\\Lock', $lock);
        $this->assertTrue($lock->isExclusive());
        $this->assertEquals(DepthHeader::INFINITY, $lock->getDepth());
        $this->assertEquals('http://www.ics.uci.edu/~ejw/contact.html', $lock->getOwner());
        $this->assertEquals('opaquelocktoken:e71d4fae-5dec-22d6-fea5-00a0c91e6be4', $lock->getToken());
        $this->assertEquals(604800, $lock->getTimeout()->getSeconds());
    }

    /**
     * Refreshing a Write Lock
     *
     * In this example, the nonce, response, and opaque fields have not been calculated in the Authorization
     * request header.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.9
     */
    public function testRefreshingWriteLock()
    {
        $response = $this->getResponseTestFixtures('LOCK', 'B');
    }

    /**
     * Multi-Resource Lock Request
     *
     * This example shows a request for an exclusive write lock on a collection and all its children. In this
     * request, the client has specified that it desires an infinite length lock, if available, otherwise a
     * timeout of 4.1 billion seconds, if available. The request entity body contains the contact information
     * for the principal taking out the lock, in this case a web page URL.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.10.10
     */
    public function testMultiResourceLockRequest()
    {
        $response = $this->getResponseTestFixtures('LOCK', 'C');
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

        $response = new Response($status);
        $this->client->setHttpClient($this->getHttpClientMock($response, 'LOCK'));
        $this->client->setThrowExceptions();
        $this->client->createLock('/resource', 'exclusive');
    }

    /**
     * In this example, the lock identified by the lock token "opaquelocktoken:a515cfa4-5da4-22e1-f5b5-00a0451e6bf7" is
     * successfully removed from the resource http://webdav.sb.aol.com/workspace/webdav/info.doc.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.11.1
     */
    public function testUnlock()
    {
        $response = new Response(204);
    }

    /**
     * Retrieving Named Properties
     *
     * In this example, PROPFIND is executed on a non-collection resource http://www.foo.bar/file. The propfind XML
     * element specifies the name of four properties whose values are being requested. In this case only two properties
     * were returned, since the principal issuing the request did not have sufficient access rights to see the third
     * and fourth properties.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.1
     */
    public function testPropfindRetrievingNamedProperties()
    {
        $request = $this->getRequestTestFixtures('PROPFIND', 'A');

        // $this->client->registerNamespace('http://www.foo.bar/boxschema/', 'R');

        $props = new PropertySet(array(
            'R:bigbox',
            'R:author',
            'R:DingALing',
            'R:Random'
        ));


/*
PROPFIND /file HTTP/1.1
Host: www.foo.bar
Content-type: text/xml; charset="utf-8"
Content-Length: xxxx


<?xml version="1.0" encoding="utf-8" ?>
<D:propfind xmlns:D="DAV:">
  <D:prop xmlns:R="http://www.foo.bar/boxschema/">
    <R:bigbox/>
    <R:author/>
    <R:DingALing/>
    <R:Random/>
  </D:prop>
</D:propfind>
*/

        $response = $this->getResponseTestFixtures('PROPFIND', 'A');
    }

    /**
     * Using allprop to Retrieve All Properties
     *
     * In this example, PROPFIND was invoked on the resource http://www.foo.bar/container/ with a Depth header of 1,
     * meaning the request applies to the resource and its children, and a propfind XML element containing the allprop
     * XML element, meaning the request should return the name and value of all properties defined on each resource.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.2
     */
    public function testPropfindUsingAllprop()
    {
        $response = $this->getResponseTestFixtures('PROPFIND', 'B');
    }

    /**
     * Using propname to Retrieve all Property Names
     *
     * In this example, PROPFIND is invoked on the collection resource http://www.foo.bar/container/, with a propfind
     * XML element containing the propname XML element, meaning the name of all properties should be returned. Since
     * no Depth header is present, it assumes its default value of "infinity", meaning the name of the properties on
     * the collection and all its progeny should be returned.
     *
     * @link http://www.webdav.org/specs/rfc2518.html#rfc.section.8.1.3
     */
    public function testPropfindUsingPropname()
    {
        $response = $this->getResponseTestFixtures('PROPFIND', 'C');
    }

    /** Data Providers **/

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

    public function getLockBadResponses()
    {
        return array(
            array(412, 'Exception\ClientFailureException', 'Precondition Failed'),
            array(423, 'Exception\ClientFailureException', 'Locked')
        );
    }

    /** Fixtures **/

    public function getRequestTestFixtures($method, $index = 'A')
    {
        return RequestFactory::getInstance()->fromMessage(
            file_get_contents(
                __DIR__ .
                DIRECTORY_SEPARATOR . 'Fixtures' .
                DIRECTORY_SEPARATOR . strtolower($method) .
                DIRECTORY_SEPARATOR . sprintf('%s-request.txt', strtoupper($index))
            )
        );
    }

    public function getResponseTestFixtures($method, $index = 'A')
    {
        return Response::fromMessage(
            file_get_contents(
                __DIR__ .
                DIRECTORY_SEPARATOR . 'Fixtures' .
                DIRECTORY_SEPARATOR . strtolower($method) .
                DIRECTORY_SEPARATOR . sprintf('%s-response.txt', strtoupper($index))
            )
        );
    }

    /** Mock Objects **/

    /**
     * @param \Guzzle\Http\Message\Response $response
     * @return \Guzzle\Http\Client
     */
    public function getHttpClientMock(Response $response, $method)
    {
        $request = $this->getMockBuilder('\Guzzle\Http\Message\Request')
                        ->disableOriginalConstructor()
                        ->setMethods(array('send', 'setHeader', 'getMethod'))
                        ->getMock();

        $request->expects($this->any())->method('getMethod')->will($this->returnValue(strtoupper($method)));

        if ($response->isError()) {
            $e = \Guzzle\Http\Exception\BadResponseException::factory($request, $response);
            $request->expects($this->any())->method('send')->will($this->throwException($e));
        } else {
            $request->expects($this->any())->method('send')->will($this->returnValue($response));
        }

        $client = $this->getMockBuilder('\Guzzle\Http\Client')
                       ->disableOriginalConstructor()
                       ->setMethods(array('createRequest'))
                       ->getMock();

        $client->expects($this->any())->method('createRequest')->will($this->returnValue($request));

        return $client;
    }
}
