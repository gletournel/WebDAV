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

use Guzzle\Http\Url;
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
    public function __construct($baseUrl = '')
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
     * @return bool Returns true on success or false on failure
     */
    public function delete($uri, $locktoken = null)
    {
        $request = $this->getHttpClient()->delete($uri);

        if ($locktoken) {
            $request->setHeader('If', "(<{$locktoken}>)");
        }

        $response = $this->doRequest($request);

        // 204 (No Content) is the default success code
        return $response->getStatusCode() == 204;
    }

    /**
     * @param string $uri
     * @param string $locktoken
     *
     * @return bool Returns true on success or false on failure
     */
    public function mkcol($uri, $locktoken = null)
    {
        $request = $this->getHttpClient()->createRequest('MKCOL', $uri);

        if ($locktoken) {
            $request->setHeader('If', "(<{$locktoken}>)");
        }

        $response = $this->doRequest($request);

        // 201 (Created) is the default success code
        return $response->getStatusCode() == 201;
    }

    /**
     *
     * The following options are available:
     * * <tt>recursive</tt>
     * * <tt>overwrite</tt>
     * * <tt>locktoken</tt>
     *
     * @param string $uri
     * @param string $dest
     * @param array  $options
     *
     * @return bool Returns true on success or false on failure
     */
    public function move($uri, $dest, array $options = null)
    {
        $recursive = isset($options['recursive']) ? (bool)$options['recursive'] : false;
        $overwrite = isset($options['overwrite']) ? (bool)$options['overwrite'] : true;

        $request = $this->getHttpClient()->createRequest('MOVE', $uri, array(
            'Destination' => $this->resolveUrl($dest),
            'Overwrite'   => $overwrite ? 'T' : 'F',
            'Depth'       => $recursive ? 'Infinity' : '0'
        ));

        if (isset($options['locktoken'])) {
            $tokens = is_array($options['locktoken']) ? $options['locktoken'] : array($options['locktoken']);

            foreach ($tokens as &$token) {
                $token = "(<{$token}>)";
            }

            $request->setHeader('If', implode(' ', $tokens));
        }

        $response = $this->doRequest($request);

        // Note that if an error occurs with a resource other than the resource
        // identified in the Request-URI then the response must be a 207 (Multi-Status)
        return $response->getStatusCode() == 201 || $response->getStatusCode() == 204;
    }

    /**
     *
     * The following options are available:
     * * <tt>recursive</tt>
     * * <tt>overwrite</tt>
     *
     * @param string $uri
     * @param string $dest    The destination path
     * @param array  $options
     *
     * @return bool Returns true on success or false on failure
     *
     * @todo Detect an attempt to copy a resource to itself, and throw an exception
     */
    public function copy($uri, $dest, array $options = null)
    {
        $recursive = isset($options['recursive']) ? (bool)$options['recursive'] : false;
        $overwrite = isset($options['overwrite']) ? (bool)$options['overwrite'] : true;

        $request = $this->getHttpClient()->createRequest('COPY', $uri, array(
            'Destination' => $this->resolveUrl($dest),
            'Overwrite'   => $overwrite ? 'T' : 'F',
            'Depth'       => $recursive ? 'Infinity' : '0'
        ));

        $response = $this->doRequest($request);

        // Note that if an error in executing the COPY method occurs with a resource other
        // than the resource identified in the Request-URI, then the response must be a 207 (Multi-Status)
        return $response->getStatusCode() == 201 || $response->getStatusCode() == 204;
    }

    /**
     * @param string $uri
     * @param int    $depth
     * @param array  $properties
     *
     * @return MultiStatus
     */
    public function propfind($uri, $depth = 0, array $properties = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $xPropfind = $dom->createElementNS('DAV:', 'D:propfind');

        if (count($properties) == 0) {
            $xProp = $dom->createElement('D:allprop');
        } else {
            $xProp = $dom->createElement('D:prop');
            $namespaces = array_flip($this->xmlNamespaces);

            foreach ($properties as $property) {
                list($prefix,) = explode(':', $property, 2);

                if ($prefix !== null && isset($namespaces[$prefix])) {
                    $xProp->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:$prefix", $namespaces[$prefix]);
                    $xPropNode = $dom->createElementNs($namespaces[$prefix], $property);
                } else {
                    $xPropNode = $dom->createElement($property);
                }

                $xProp->appendChild($xPropNode);
            }
        }

        $dom->appendChild($xPropfind)->appendChild($xProp);
        $body = $dom->saveXML();

        $request = $this->getHttpClient()->createRequest('PROPFIND', $uri, array(
            'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
            'Depth' => $depth
        ), $body);

        $response = $this->doRequest($request);

        return $response->getStatusCode() == 207 ? MultiStatus::parse($this, $response->getBody()) : null;
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
            'Content-Type' => 'text/xml; charset="utf-8"',
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

        return $response->isSuccessful() ? Lock::parse($this, $response->getBody()) : null;
    }

    /**
     * @param string $uri
     * @param string $token
     * @param int    $timeout
     *
     * @return Lock Returns the refreshed lock on success, or <tt>null</tt> on failure
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

        return $response->isSuccessful() ? Lock::parse($this, $response->getBody()) : null;
    }

    /**
     * @param string $uri
     * @param string $token
     *
     * @return bool Returns true on success or false on failure
     */
    public function releaseLock($uri, $token)
    {
        $request = $this->getHttpClient()->createRequest('UNLOCK', $uri, array(
            'Lock-Token' => "<{$token}>"
        ));

        $response = $this->doRequest($request);

        // 204 (No Content) is the default success code
        return $response->getStatusCode() == 204;
    }

    /**
     * @param string $uri
     * @return array
     */
    public function getAllowedMethods($uri = null)
    {
        $methods = array();

        $request  = $this->getHttpClient()->options($uri);
        $response = $this->doRequest($request);

        if ($response->hasHeader('Allow')) {
            foreach (explode(',', $response->getHeader('Allow')) as $method) {
                $methods[] = trim($method);
            }
        }

        return $methods;
    }

    /**
     * @param string $uri
     * @return array
     */
    public function getCompliance($uri = null)
    {
        $classes = array();

        $request  = $this->getHttpClient()->options($uri);
        $response = $this->doRequest($request);

        if ($response->hasHeader('Dav')) {
            foreach (explode(',', $response->getHeader('Dav')) as $class) {
                $classes[] = trim($class);
            }
        }

        return $classes;
    }





    public function resolveUrl($uri)
    {
        // Use absolute URLs as-is
        if (substr($uri, 0, 4) == 'http') {
            $url = $uri;
        } else {
            $url = Url::factory($this->baseUrl)->combine($uri);
        }

        return (string)$url;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
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
        return $this->lastRequest ? (string)$this->lastRequest : null;
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

        $this->getHttpClient()->setUserAgent($userAgent);
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
     *
     * @return self
     */
    public function setThrowExceptions($throwExceptions = true)
    {
        $this->throwExceptions = (bool)$throwExceptions;
        return $this;
    }

    /**
     * @param HttpClient $client
     *
     * @internal
     */
    public function setHttpClient(HttpClient $client)
    {
        $client->setBaseUrl($this->baseUrl)
               ->setUserAgent($this->userAgent);

        if ($this->authType) {
            $client->setDefaultOption('auth', array($this->username, $this->password, $this->authType));
        }

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
