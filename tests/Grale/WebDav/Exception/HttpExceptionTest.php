<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Grale\WebDav\Exception;

use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Exception\BadResponseException;

/**
 * @covers Grale\WebDav\Exception\HttpException
 */
class HttpExceptionTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = RequestFactory::getInstance()->fromMessage(
            "GET /container/ HTTP/1.1\r\n" .
            "Host: www.foo.bar\r\n"
        );
    }

    public function testClientFailureException()
    {
        $response = new Response(400);

        $e = BadResponseException::factory(
            $this->request,
            $response
        );

        $httpException = HttpException::factory($e);

        $this->assertInstanceOf('\Grale\WebDav\Exception\ClientFailureException', $httpException);
        $this->assertEquals((string)$this->request, $httpException->getRequest());
        $this->assertEquals((string)$response, $httpException->getResponse());
        $this->assertEquals(400, $httpException->getStatusCode());
    }

    public function testServerFailureException()
    {
        $response = new Response(500);

        $e = BadResponseException::factory(
            $this->request,
            $response
        );

        $httpException = HttpException::factory($e);

        $this->assertInstanceOf('\Grale\WebDav\Exception\ServerFailureException', $httpException);
        $this->assertEquals((string)$this->request, $httpException->getRequest());
        $this->assertEquals((string)$response, $httpException->getResponse());
        $this->assertEquals(500, $httpException->getStatusCode());
    }

    /**
     * @dataProvider getErrorMapping
     * @param string $httpMethod
     * @param int    $statusCode
     * @param string $description
     */
    public function testErrorDescriptions($httpMethod, $statusCode, $description)
    {
        $request = RequestFactory::getInstance()->fromMessage(
            "$httpMethod /container/ HTTP/1.1\r\n" .
            "Host: www.foo.bar\r\n"
        );

        $response = new Response($statusCode);

        $prevException = BadResponseException::factory($request, $response);
        $httpException = HttpException::factory($prevException);

        $this->assertEquals($description, $httpException->getDescription());
    }

    public function getErrorMapping()
    {
        return array(
            array('MOVE', 403, 'Source and destination URIs are the same'),
            array('MOVE', 409, 'One or more parent collections are not found'),
            array('MOVE', 412, 'The server was unable to maintain the availability of the properties'),
            array('MOVE', 423, 'The source or the destination resource was locked'),
            array('MOVE', 502, 'The destination server refuses to accept the resource'),
            array('COPY', 403, 'Source and destination URIs are the same'),
            array('COPY', 409, 'One or more parent collections are not found'),
            array('COPY', 412, 'The server was unable to maintain the availability of the properties'),
            array('COPY', 423, 'The destination resource was locked'),
            array('COPY', 502, 'The destination server refuses to accept the resource'),
            array('COPY', 507, 'Insufficient storage'),
            array('LOCK', 412, 'The lock token could not be enforced'),
            array('LOCK', 423, 'The resource is already locked'),
            array('MKCOL', 403, 'Permissions denied'),
            array('MKCOL', 405, 'The resource already exists'),
            array('MKCOL', 409, 'Cannot create a resource if all ancestors do not already exist'),
            array('MKCOL', 415, 'The server does not support the request type of the body'),
            array('MKCOL', 507, 'Insufficient storage'),
            array('PROPPATCH', 403, 'Properties cannot be set or removed'),
            array('PROPPATCH', 409, 'Cannot set property to value provided'),
            array('PROPPATCH', 423, 'The resource is locked'),
            array('PROPPATCH', 507, 'Insufficient storage')
        );
    }
}
