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

use Guzzle\Http\Client as HttpClient;
use Guzzle\Http\Message\Response as HttpResponse;
use Guzzle\Http\Message\RequestInterface as HttpRequest;
use Guzzle\Http\Exception\BadResponseException;
use Grale\WebDav\Exception\NoSuchResourceException;
use Grale\WebDav\Exception\AccessDeniedException;
use Grale\WebDav\Exception\HttpException;
use Grale\WebDav\Header\TimeoutHeader;
use Grale\WebDav\Header\DepthHeader;

/**
 * WebDAV client
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * @todo implement the PROPATCH method
 */
class Client
{
    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var HttpRequest The content of the last sent request
     */
    protected $lastRequest;

    /**
     * @var HttpResponse The last received HTTP response
     */
    protected $lastResponse;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string The HTTP authentication method to use
     */
    protected $authType;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string The contents of the "User-Agent" header to be used in HTTP requests
     */
    protected $userAgent;

    /**
     * @var bool Wether or not exceptions should be thrown when a HTTP error is returned
     */
    protected $throwExceptions = false;

    /**
     * @var array A default list of namespaces
     */
    public $xmlNamespaces = array('DAV:' => 'D');

    /**
     * A key-value array of WebDAV properties
     *
     * If you use the property map, any <tt>multistatus</tt> responses with the properties listed in this array,
     * will automatically be mapped to a respective class.
     *
     * Note that the following properties are automatically added to the map.
     *
     * <code>
     *  'resourcetype'    => 'Grale\\WebDav\\Property\\ResourceType',
     *  'creationdate'    => 'Grale\\WebDav\\Property\\DateTimeProperty',
     *  'getlastmodified' => 'Grale\\WebDav\\Property\\DateTimeProperty',
     *  'lockdiscovery'   => 'Grale\\WebDav\\Property\\LockDiscovery',
     *  'supportedlock'   => 'Grale\\WebDav\\Property\\SupportedLock',
     * </code>
     *
     * @var array
     */
    public $propertyMap = array();

    /**
     * @param string $baseUrl
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl   = $baseUrl;
        $this->userAgent = $this->getDefaultUserAgent();

        $this->propertyMap = array(
            'resourcetype'    => __NAMESPACE__ . '\\Property\\ResourceType',
            'creationdate'    => __NAMESPACE__ . '\\Property\\DateTimeProperty',
            'getlastmodified' => __NAMESPACE__ . '\\Property\\DateTimeProperty',
            'lockdiscovery'   => __NAMESPACE__ . '\\Property\\LockDiscovery',
            'supportedlock'   => __NAMESPACE__ . '\\Property\\SupportedLock'
        );
    }

    /**
     * @return bool
     */
    public function exists($uri = null)
    {
        return false;
    }

    /**
     * @return array
     */
    public function options($uri = null)
    {
        $features = array();

        $request  = $this->getHttpClient()->options($uri);
        $response = $this->doRequest($request);

        if ($response->hasHeader('Dav')) {
            foreach (explode(',', $response->getHeader('Dav')) as $feat) {
                $features[] = trim($feat);
            }
        }

        return $features;
    }

    /**
     * @param string $uri
     *
     * @return array
     */
    public function head($uri)
    {
        $request  = $this->getHttpClient()->head($uri);
        $response = $this->doRequest($request);

        return $response;
    }

    /**
     * @param string $uri
     *
     * @return string
     */
    public function get($uri)
    {
        $request = $this->getHttpClient()->get($uri);
        return $this->doRequest($request);
    }

    /**
     * @param string          $uri
     * @param array           $headers
     * @param string|resource $body
     *
     * @return bool
     */
    public function put($uri, array $headers = null, $body = null)
    {
        $request = $this->getHttpClient()->put($uri, $headers, $body);
        return $this->doRequest($request);
    }

    /**
     * @param string $uri
     * @param string $locktoken
     *
     * @return bool
     */
    public function delete($uri, $locktoken = null)
    {
        $request = $this->getHttpClient()->delete($uri);

        if ($locktoken) {
            $request->setHeader('If', "(<{$locktoken}>)");
        }

        return $this->doRequest($request);
    }

    /**
     * @param string $uri
     * @param string $locktoken
     *
     * @return bool
     * @throws HttpException
     */
    public function mkcol($uri, $locktoken = null)
    {
        $request = $this->getHttpClient()->createRequest('MKCOL', $uri);

        if ($locktoken) {
            $request->setHeader('If', "(<{$locktoken}>)");
        }

        return $this->doRequest($request);
    }

    /**
     * @param string $uri
     * @param string $dest
     * @param int    $options (DEPTH_NONE, DEPTH_INFINITY, OVERWRITE, PROPERTY_BEHAVIOR_OMIT, PROPERTY_BEHAVIOR_KEEPALIVE)
     * @param array  $keepalive
     *
     * @return bool
     * @throws HttpException
     */
    public function move($uri, $dest, $options = null, array $keepalive = null)
    {
        $request = $this->getHttpClient()->createRequest('MOVE', $uri, array(
            'Destination' => $dest
        ));

        return $this->doRequest($request);
    }

    /**
     * @param string $uri
     * @param string $dest
     * @param int    $options (DEPTH_NONE, DEPTH_INFINITY, OVERWRITE, PROPERTY_BEHAVIOR_OMIT, PROPERTY_BEHAVIOR_KEEPALIVE)
     * @param array  $keepalive
     *
     * @return bool
     * @throws HttpException
     */
    public function copy($uri, $dest, $options = null, array $keepalive = null)
    {
        $request = $this->getHttpClient()->createRequest('COPY', $uri, array(
            'Destination' => $dest
        ));

        return $this->doRequest($request);
    }

    /**
     * @param string $uri
     * @param int    $depth
     * @param array  $properties
     *
     * @return MultiStatus
     * @throws HttpException
     */
    public function propfind($uri, $depth = 0, array $properties = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElementNS('DAV:', 'D:propfind');

        if (count($properties) == 0) {
            $prop = $dom->createElement('D:allprop');
        } else {
            $prop = $dom->createElement('D:prop');
        }

        $dom->appendChild($root)->appendChild($prop);
        $body = $dom->saveXML();

        $request = $this->getHttpClient()->createRequest('PROPFIND', $uri, array(
            'Depth' => $depth,
            'Content-Type' => 'application/xml'
        ), $body);

        $response = $this->doRequest($request);

        return MultiStatus::parse($this, $response->getBody());
    }

    /**
     *
     * Available options:
     * * <tt>timeout</tt>
     * * <tt>owner</tt>
     *
     * Note that the <tt>depth</tt> option is unused.
     * Note also that only write locks are supported.
     *
     * @param string $uri
     * @param string $scope   The locking mechanism to use ({@link Lock::EXCLUSIVE} or {@link Lock::SHARED} lock)
     * @param array  $options The locking options
     *
     * @return Lock Returns the created lock on success, or <tt>null</tt> on failure
     *
     * @throws \InvalidArgumentException When the locking mechanism specified is not supported
     *
     * @throws \RuntimeException
     *         When the server returns an unexpected response. Actually, 207 (Multi-Status) responses are not supposed
     *         to be received from server, as far as multi-resource lock requests are not supported.
     */
    public function createLock($uri, $scope, array $options = null)
    {
        if ($scope != Lock::EXCLUSIVE && $scope != Lock::SHARED) {
            throw new \InvalidArgumentException('The locking mechanism specified is not supported');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElementNS('DAV:', 'D:lockinfo');
        $root->appendChild($dom->createElement('D:lockscope'))->appendChild($dom->createElement("D:{$scope}"));
        $root->appendChild($dom->createElement('D:locktype'))->appendChild($dom->createElement('D:write'));

        if (isset($options['owner'])) {
            $xOwner = $dom->createElementNS('DAV:', 'D:owner');
            $xHref  = $dom->createElementNS('DAV:', 'D:href', $options['owner']);
            $root->appendChild($xOwner)->appendChild($xHref);
        }

        $dom->appendChild($root);
        $body = $dom->saveXML();

        $request = $this->getHttpClient()->createRequest('LOCK', $uri, array(
            'Content-Type' => 'application/xml',
            'Depth'        => '0'
        ), $body);

        if (isset($options['timeout'])) {
            $request->setHeader('Timeout', (string)TimeoutHeader::parse($options['timeout']));
        }

        $response = $this->doRequest($request);

        // When the LOCK request succeeds, the lockdiscovery property is included in the response body.
        // However note that multi-resource lock requests are not supported, so that 207 (Multi-Status)
        // responses are not supposed to be returned

        if ($response->getStatusCode() == 207) {
            throw new \RuntimeException('Unexpected server response');
        }

        return $response->isSuccessful() ? Lock::parse($this, $response->getBody()) : false;
    }

    /**
     * @param string $uri
     * @param string $token
     * @param int    $timeout
     *
     * @return Lock
     * @throws HttpException
     */
    public function refreshLock($uri, $token, $timeout = null)
    {
        $request = $this->getHttpClient()->createRequest('LOCK', $uri, array(
            'If' => "(<{$token}>)"
        ));

        if ($timeout) {
            $request->setHeader('Timeout', (string)TimeoutHeader::parse($timeout));
        }

        $response = $this->doRequest($request);
    }

    /**
     * @param string $uri
     * @param string $token
     *
     * @return bool Returns true on success or false on failure
     * @throws HttpException
     */
    public function releaseLock($uri, $token)
    {
        $request = $this->getHttpClient()->createRequest('UNLOCK', $uri, array(
            'Lock-Token' => "<{$token}>"
        ));

        $response = $this->doRequest($request);

        // OK   204 (No Content)
        //      409 (Conflict)
        // KO   400 (Bad Request)
        //      403 (Forbidden)

        return true;
    }









    /**
     * Sends a single request to a WebDAV server
     *
     * @param HttpRequest $request The request
     *
     * @return HttpResponse Returns the server response
     * @throws HttpException If an HTTP error is returned
     */
    protected function doRequest(HttpRequest $request)
    {
        $error    = null;
        $response = null;

        $this->lastRequest  = $request;
        $this->lastResponse = null;

        try {
            $response = $request->send();
        } catch (BadResponseException $error) {
            $response = $error->getResponse();
        }

        // Creates History
        $this->lastResponse = $response;

        if ($error && $this->throwExceptions) {
            switch ($response->getStatusCode()) {
                case 404:
                    throw new NoSuchResourceException('No such file or directory');
                default:
                    throw HttpException::factory($error);
            }
        }

        return $response;
    }

    /**
     * @return string Returns the content of the last sent request
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * @return string Returns the content of the last received response
     */
    public function getLastResponse()
    {
        return $this->lastResponse ? $this->lastResponse->getBody(true) : null;
    }

    /**
     * @return array Returns the content of the last received response
     */
    public function getLastResponseHeaders()
    {
        return $this->lastResponse ? $this->lastResponse->getHeaders() : array();
    }

    /**
     * @return int Returns The HTTP status-code of the last received response
     */
    public function getLastResponseStatus()
    {
        return $this->lastResponse ? $this->lastResponse->getStatusCode() : null;
    }

    /**
     * @return bool Returns true on success or false on failure
     * @throws \RuntimeException If a stream wrapper has already been registered
     */
    public function registerStreamWrapper()
    {
        return StreamWrapper::register($this);
    }

    /**
     * @return string
     */
    public function getDefaultUserAgent()
    {
        return 'Grale/' . Version::VERSION . ' PHP/' . PHP_VERSION;
    }

    /**
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @param string|bool $user
     * @param string      $password
     * @param string      $scheme
     */
    public function setAuth($user, $password = '', $scheme = 'Basic')
    {
        if ($user === null or $user === false) {
            $this->username = $this->password = $this->authType = null;
        } else {
            $this->username = $user;
            $this->password = $password;
            $this->authType = $scheme;
        }
    }

    /**
     * @param string|bool $certificateAuthority
     * @param bool        $verifyPeer
     */
    public function setSslVerify($certificateAuthority = true, $verifyPeer = true)
    {
        $this->getHttpClient()->setSslVerification($certificateAuthority, $verifyPeer);
    }

    /**
     * @param bool $throwExceptions
     */
    public function setThrowExceptions($throwExceptions = true)
    {
        $this->throwExceptions = (bool)$throwExceptions;
    }

    /**
     * @param HttpClient $client
     *
     * @internal
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;
    }

    /**
     * @return HttpClient
     *
     * @internal
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = $this->getDefaultHttpClient();
        }

        return $this->httpClient;
    }

    /**
     * @return HttpClient
     *
     * @internal
     */
    protected function getDefaultHttpClient()
    {
        $httpClient = new HttpClient($this->baseUrl);
        $httpClient->setUserAgent($this->userAgent);

        if ($this->authType) {
            $httpClient->setDefaultOption('auth', array($this->username, $this->password, $this->authType));
        }

        return $httpClient;
    }
}
