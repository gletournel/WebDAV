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
use Guzzle\Http\EntityBody;
use Guzzle\Http\Client as HttpClient;
use Guzzle\Http\Message\Response as HttpResponse;
use Guzzle\Http\Message\RequestInterface as HttpRequest;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Stream\PhpStreamRequestFactory;
use Grale\WebDav\Exception\NoSuchResourceException;
use Grale\WebDav\Exception\HttpException;
use Grale\WebDav\Header\TimeoutHeader;

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
     * @var string The request options
     */
    protected $requestOptions;

    /**
     * @var string The base URL of the client
     */
    protected $baseUrl;

    /**
     * @var string The contents of the "User-Agent" header to be used in HTTP requests
     */
    protected $userAgent;

    /**
     * @var bool Whether or not exceptions should be thrown when a HTTP error is returned
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
     * @param string $baseUrl The base URL of the client
     * @param array  $config  Configuration settings
     * @see setConfig
     */
    public function __construct($baseUrl = '', array $config = null)
    {
        $this->propertyMap = array(
            'resourcetype'    => __NAMESPACE__ . '\\Property\\ResourceType',
            'creationdate'    => __NAMESPACE__ . '\\Property\\DateTimeProperty',
            'getlastmodified' => __NAMESPACE__ . '\\Property\\DateTimeProperty',
            'lockdiscovery'   => __NAMESPACE__ . '\\Property\\LockDiscovery',
            'supportedlock'   => __NAMESPACE__ . '\\Property\\SupportedLock'
        );

        $this->setBaseUrl($baseUrl);
        $this->userAgent = $this->getDefaultUserAgent();
        $this->requestOptions = array();

        if ($config !== null) {
            $this->setConfig($config);
        }
    }

    /**
     * Get the content of the specified resource.
     *
     * @param string $uri Resource URI
     * @return string Returns the contents of this resource or <tt>null</tt> on failure
     */
    public function get($uri)
    {
        $request  = $this->createRequest('GET', $uri);
        $response = $this->doRequest($request);

        return $response->isSuccessful() ? $response->getBody(true) : null;
    }

    /**
     * Get the streaming contents of the specified resource.
     *
     * @param string $uri Resource URI
     *
     * @return EntityBody Returns the stream resource on success or false on failure
     * @throws \RuntimeException If the stream cannot be opened or an error occurs
     */
    public function getStream($uri)
    {
        $request = $this->createRequest('GET', $uri);

        $factory = new PhpStreamRequestFactory();
        $stream  = $factory->fromRequest($request, array(), array('stream_class' => 'Guzzle\Http\EntityBody'));

        // The implementation of streaming download proposed by Guzzle's EntityBody class does not care about
        // HTTP errors. As a workaround, let's rebuild the HTTP response from the response headers sent in the
        // $http_response_header variable (http://www.php.net/manual/en/reserved.variables.httpresponseheader.php)
        $response = HttpResponse::fromMessage(implode("\r\n", $factory->getLastResponseHeaders()));

        // Creates History
        $this->lastRequest  = $request;
        $this->lastResponse = $response;

        if (!$response->isSuccessful()) {
            $stream = false;
        }

        if (!$stream && $this->throwExceptions) {
            switch ($response->getStatusCode()) {
                case 404:
                    throw new NoSuchResourceException('No such file or directory');
                default:
                    throw new \RuntimeException($response->getReasonPhrase(), $response->getStatusCode());
            }
        }

        return $stream;
    }

    /**
     * Check whether the specified resource exists.
     *
     * @param string $uri Resource URI
     * @return bool Returns true if this resource represents an existing item or false otherwise
     */
    public function exists($uri)
    {
        $request  = $this->createRequest('HEAD', $uri);
        $response = $this->doRequest($request);

        return $response->getStatusCode() == 200;
    }

    /**
     * Write data to the specified resource.
     *
     * Performs a <tt>PUT</tt> request following the requirements described in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.7 Section 9.7 of RFC-4918}.
     *
     * The following options are available:
     * - <tt>headers</tt>, an associative array of HTTP headers
     * - <tt>locktoken</tt>
     *
     * @param string          $uri     Resource URI
     * @param string|resource $body    Data to write
     * @param array           $options Options to apply to the request
     *
     * @return bool Returns true on success or false on failure
     */
    public function put($uri, $body = null, array $options = null)
    {
        $headers = isset($options['headers']) ? $options['headers'] : array();
        $request = $this->createRequest('PUT', $uri, $headers, $body);

        if (isset($options['locktoken'])) {
            $request->setHeader('If', '(<' . $options['locktoken'] . '>)');
        }

        $response = $this->doRequest($request);

        // 201 (Created) is the default success code
        return $response->getStatusCode() == 201;
    }

    /**
     * Delete the specified resource.
     *
     * Performs a <tt>DELETE</tt> request following the requirements described in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.6 Section 9.6 of RFC-4918}.
     *
     * The following options are available:
     * - <tt>headers</tt>, an associative array of HTTP headers
     * - <tt>locktoken</tt>
     *
     * @param string $uri     Resource URI
     * @param array  $options Options to apply to the request
     *
     * @return bool Returns true on success or false on failure
     */
    public function delete($uri, array $options = null)
    {
        $headers = isset($options['headers']) ? $options['headers'] : array();
        $request = $this->createRequest('DELETE', $uri, $headers);

        if (isset($options['locktoken'])) {
            $request->setHeader('If', '(<' . $options['locktoken'] . '>)');
        }

        $response = $this->doRequest($request);

        // 204 (No Content) is the default success code
        return $response->getStatusCode() == 204;
    }

    /**
     * Create a new collection resource at the location specified.
     *
     * Performs a <tt>MKCOL</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.3 Section 9.3 of RFC-4918}.
     *
     * The following options are available:
     * - <tt>headers</tt>, an associative array of HTTP headers
     * - <tt>locktoken</tt>
     *
     * @param string $uri     Resource URI
     * @param array  $options Options to apply to the request
     *
     * @return bool Returns true on success or false on failure
     */
    public function mkcol($uri, array $options = null)
    {
        $headers = isset($options['headers']) ? $options['headers'] : array();
        $request = $this->createRequest('MKCOL', $uri, $headers);

        if (isset($options['locktoken'])) {
            $request->setHeader('If', '(<' . $options['locktoken'] . '>)');
        }

        $response = $this->doRequest($request);

        // 201 (Created) is the default success code
        return $response->getStatusCode() == 201;
    }

    /**
     * Move the specified resource to the given destination resource.
     *
     * Performs a <tt>MOVE</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.9 Section 9.9 of RFC-4918}.
     *
     * The following options are available:
     * - <tt>recursive</tt>
     * - <tt>overwrite</tt>
     * - <tt>locktoken</tt>
     *
     * @param string $uri         Resource URI
     * @param string $destination URI of the destination resource
     * @param array  $options     Options to apply to the request
     *
     * @return bool Returns true on success or false on failure
     */
    public function move($uri, $destination, array $options = null)
    {
        $recursive = isset($options['recursive']) ? (bool)$options['recursive'] : false;
        $overwrite = isset($options['overwrite']) ? (bool)$options['overwrite'] : true;

        $request = $this->createRequest('MOVE', $uri, array(
            'Destination' => $this->resolveUrl($destination),
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
     * Copy the specified resource to the given destination resource.
     *
     * Performs a <tt>COPY</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.8 Section 9.8 of RFC-4918}.
     *
     * The following options are available:
     * - <tt>recursive</tt>
     * - <tt>overwrite</tt>
     *
     * @param string $uri         Resource URI
     * @param string $destination URI of the destination resource
     * @param array  $options     Options to apply to the request
     *
     * @return bool Returns true on success or false on failure
     *
     * @todo Detect an attempt to copy a resource to itself, and throw an exception
     */
    public function copy($uri, $destination, array $options = null)
    {
        $recursive = isset($options['recursive']) ? (bool)$options['recursive'] : false;
        $overwrite = isset($options['overwrite']) ? (bool)$options['overwrite'] : true;

        $request = $this->createRequest('COPY', $uri, array(
            'Destination' => $this->resolveUrl($destination),
            'Overwrite'   => $overwrite ? 'T' : 'F',
            'Depth'       => $recursive ? 'Infinity' : '0'
        ));

        $response = $this->doRequest($request);

        // Note that if an error in executing the COPY method occurs with a resource other
        // than the resource identified in the Request-URI, then the response must be a 207 (Multi-Status)
        return $response->getStatusCode() == 201 || $response->getStatusCode() == 204;
    }

    /**
     * Retrieve properties defined on the specified resource.
     *
     * Performs a <tt>PROPFIND</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.1 Section 9.1 of RFC-4918}.
     *
     * The following options are available:
     * - <tt>properties</tt>
     * - <tt>depth</tt>
     *
     * @param string $uri     Resource URI
     * @param array  $options Options to apply to the request
     *
     * @return MultiStatus
     */
    public function propfind($uri, array $options = null)
    {
        $depth      = isset($options['depth']) ? (int)$options['depth'] : 0;
        $properties = isset($options['properties']) ? $options['properties'] : array();

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

        $request = $this->createRequest('PROPFIND', $uri, array(
            'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
            'Depth' => $depth
        ), $body);

        $response = $this->doRequest($request);

        return $response->getStatusCode() == 207 ? MultiStatus::parse($this, $response->getBody()) : null;
    }

    /**
     * Create a new lock for the given resource.
     *
     * Performs a <tt>LOCK</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.10.1 Section 9.10.1 of RFC-4918}.
     *
     * Available options:
     * - <tt>type</tt>, the lock type. Note that only write locks are supported.
     * - <tt>scope</tt>, the locking mechanism to use ({@link Lock::EXCLUSIVE} or {@link Lock::SHARED} lock).
     * - <tt>timeout</tt>
     * - <tt>owner</tt>
     *
     * @param string $uri     Resource URI
     * @param array  $options Locking options
     *
     * @return Lock Returns the created lock on success, or <tt>null</tt> on failure
     *
     * @throws \InvalidArgumentException When the locking mechanism specified is not supported
     *
     * @throws \RuntimeException
     *         When the server returns an unexpected response. Actually, 207 (Multi-Status) responses are not supposed
     *         to be received from server, as far as multi-resource lock requests are not supported.
     */
    public function createLock($uri, array $options = null)
    {
        $scope = isset($options['scope']) ? $options['scope'] : Lock::EXCLUSIVE;

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

        $headers = array(
            'Content-Type' => 'text/xml; charset="utf-8"',
            'Depth'        => '0'
        );

        if (isset($options['timeout'])) {
            $headers['Timeout'] = (string)TimeoutHeader::parse($options['timeout']);
        }

        $request  = $this->createRequest('LOCK', $uri, $headers, $body);
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
     * Refresh an existing lock by resetting its timeout.
     *
     * Performs a <tt>LOCK</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.10.2 Section 9.10.2 of RFC-4918}.
     *
     * Note that the timeout value may be suggested when refreshing the lock, but that the server
     * ultimately chooses the timeout value.
     *
     * @param string $uri       Resource URI
     * @param string $lockToken The lock token identifying the lock to be refreshed
     * @param int    $timeout   Number of seconds remaining until lock expiration
     *
     * @return Lock Returns the refreshed lock on success, or <tt>null</tt> on failure
     */
    public function refreshLock($uri, $lockToken, $timeout = null)
    {
        $headers = array(
            'If' => "(<{$lockToken}>)"
        );

        if ($timeout) {
            $headers['Timeout'] = (string)TimeoutHeader::parse($timeout);
        }

        $request  = $this->createRequest('LOCK', $uri, $headers);
        $response = $this->doRequest($request);

        return $response->isSuccessful() ? Lock::parse($this, $response->getBody()) : null;
    }

    /**
     * Release the lock identified by the given lock token.
     *
     * Performs an <tt>UNLOCK</tt> request as defined in the
     * {@link http://tools.ietf.org/html/rfc4918#section-9.11 Section 9.11 of RFC-4918}.
     *
     * @param string $uri       Resource URI
     * @param string $lockToken The lock token identifying the lock to be removed
     *
     * @return bool Returns true on success or false if the lock could not be removed
     */
    public function releaseLock($uri, $lockToken)
    {
        $headers = array(
            'Lock-Token' => "<{$lockToken}>"
        );

        $request  = $this->createRequest('UNLOCK', $uri, $headers);
        $response = $this->doRequest($request);

        // 204 (No Content) is the default success code
        return $response->getStatusCode() == 204;
    }

    /**
     * Returns a list of all HTTP methods supported by the given resource.
     *
     * @param string $uri Resource URI
     * @return array Methods supported by this resource
     */
    public function getSupportedMethods($uri = null)
    {
        $methods = array();

        $request  = $this->createRequest('OPTIONS', $uri);
        $response = $this->doRequest($request);

        if ($response->hasHeader('Allow')) {
            foreach (explode(',', $response->getHeader('Allow')) as $method) {
                $methods[] = trim($method);
            }
        }

        return $methods;
    }

    /**
     * Returns a list of all compliance classes the given resource is fulfilling.
     *
     * @param string $uri Resource URI
     * @return array Compliance classes
     */
    public function getComplianceClasses($uri = null)
    {
        $classes = array();

        $request  = $this->createRequest('OPTIONS', $uri);
        $response = $this->doRequest($request);

        if ($response->hasHeader('Dav')) {
            foreach (explode(',', $response->getHeader('Dav')) as $class) {
                $classes[] = trim($class);
            }
        }

        return $classes;
    }

    /**
     * Combine the given resource URI with the base URL of the client.
     *
     * @param string $uri Resource URI
     * @return string
     */
    protected function resolveUrl($uri)
    {
        // Use absolute URLs as-is
        if (substr($uri, 0, 4) == 'http') {
            $url = $uri;
        } else {
            $url = Url::factory($this->baseUrl)->combine($uri);
        }

        return (string)$url;
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
     * Create a new request configured for the client.
     *
     * @param string          $method  HTTP method
     * @param string          $uri     Resource URI
     * @param array           $headers HTTP headers
     * @param string|resource $body    Entity body of request
     *
     * @return HttpRequest Returns the created request
     */
    protected function createRequest($method, $uri, array $headers = null, $body = null)
    {
        $url = $this->resolveUrl($uri);

        $request = $this->getHttpClient()->createRequest($method, $url, $headers, $body, $this->requestOptions);
        $request->setHeader('User-Agent', $this->userAgent);

        return $request;
    }

    /**
     * Register the WebDAV stream wrapper.
     *
     * @return bool Returns true on success or false on failure
     * @throws \RuntimeException If a stream wrapper has already been registered
     */
    public function registerStreamWrapper()
    {
        return StreamWrapper::register($this->getContextOptions(), $this);
    }

    /**
     * Get the context options for the WebDAV stream wrapper.
     *
     * @return array Returns context options and parameters which can be used with the WebDAV stream wrapper
     */
    public function getContextOptions()
    {
        $options = array(
            'base_url'   => $this->baseUrl,
            'user_agent' => $this->userAgent
        );

        return $options + $this->requestOptions;
    }

    /**
     * Get the last sent request.
     * @return string Returns the content of the last sent request
     */
    public function getLastRequest()
    {
        return $this->lastRequest ? (string)$this->lastRequest : null;
    }

    /**
     * Get the last received response.
     * @return string Returns the body of the last received response
     */
    public function getLastResponse()
    {
        return $this->lastResponse ? $this->lastResponse->getBody(true) : null;
    }

    /**
     * Get the list of headers received in the last response.
     * @return array Returns the headers of the last received response
     */
    public function getLastResponseHeaders()
    {
        return $this->lastResponse ? $this->lastResponse->getHeaders() : array();
    }

    /**
     * Get the status-code of the last received response.
     * @return int Returns The HTTP status-code of the last received response
     */
    public function getLastResponseStatus()
    {
        return $this->lastResponse ? $this->lastResponse->getStatusCode() : null;
    }

    /**
     * Get the default User-Agent string to use with the client.
     * @return string Returns a string identifying the client version
     */
    public function getDefaultUserAgent()
    {
        return 'Grale/' . Version::VERSION . ' PHP/' . PHP_VERSION;
    }

    /**
     * Get the base URL of the client.
     * @return string The base URL
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set the base URL of the client.
     *
     * @param string $url The base URL
     * @return self Provides a fluent interface
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Set the client configuration.
     *
     * The client supports the following parameters:
     * - <tt>auth</tt>              — the HTTP authorization parameters. Set to false to disable authentication
     *                                or pass an array containing user name, password and authentication scheme
     *                                as required by {@link setAuth}.
     *
     * - <tt>base_url</tt>          — the base URL of the client.
     *
     * - <tt>user_agent</tt>        — the user-agent string to use in HTTP requests.
     *
     * - <tt>ssl_verify</tt>        — set to false to stop from verifying the peer's certificate or to the path
     *                                of a file holding one or more certificates to verify the peer with.
     *
     * - <tt>ssl_key</tt>           — path to a file containing a private SSL key, or false to disable the SSL
     *                                private key. If a secret password is needed to use the private SSL key,
     *                                use an array containing the path to key followed by the secret password.
     *
     * - <tt>ssl_certificate</tt>   — path to a file containing a PEM formatted certificate, or false to disable
     *                                the SSL certificate. If a password is required with the certificate, use
     *                                an array containing the path to certification followed by its password.
     *
     * - <tt>throw_exceptions</tt>  — a boolean value indicating whether or not exceptions should be thrown when
     *                                an HTTP error is returned.
     *
     * @param array $config Parameters that define how the client behaves
     * @return self Provides a fluent interface
     */
    public function setConfig(array $config)
    {
        if (isset($config['auth'])) {
            $params = is_array($config['auth']) ? $config['auth'] : array($config['auth']);
            call_user_func_array(array($this, 'setAuth'), $params);
        }

        foreach ($config as $key => $value) {
            switch ($key) {
                case 'base_url':
                    $this->setBaseUrl($value);
                    break;
                case 'user_agent':
                    $this->setUserAgent($value);
                    break;
                case 'ssl_verify':
                    $this->requestOptions['verify'] = $value;
                    break;
                case 'ssl_certificate':
                    if ($value === null or $value === false) {
                        unset($this->requestOptions['cert']);
                    } else {
                        $this->requestOptions['cert'] = $value;
                    }
                    break;
                case 'ssl_key':
                    if ($value === null or $value === false) {
                        unset($this->requestOptions['ssl_key']);
                    } else {
                        $this->requestOptions['ssl_key'] = $value;
                    }
                    break;
                case 'throw_exceptions':
                    $this->setThrowExceptions($value);
                    break;
            }
        }

        return $this;
    }

    /**
     * Set the "User-Agent" header to be used on all requests.
     *
     * @param string $userAgent User agent string
     * @return self Provides a fluent interface
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set HTTP authorization parameters.
     *
     * @param string|bool $user     Username or false to disable authentication
     * @param string      $password Password
     * @param string      $scheme   Authentication scheme (<tt>Basic</tt> or <tt>Digest</tt>)
     *
     * @return self Provides a fluent interface
     */
    public function setAuth($user, $password = '', $scheme = 'Basic')
    {
        if ($user === null or $user === false) {
            unset($this->requestOptions['auth']);
        } else {
            $this->requestOptions['auth'] = array($user, $password, $scheme);
        }

        return $this;
    }

    /**
     * Set whether exceptions should be thrown when an HTTP error is returned.
     *
     * @param bool $throwExceptions true if exceptions should be thrown
     * @return self Provides a fluent interface
     */
    public function setThrowExceptions($throwExceptions = true)
    {
        $this->throwExceptions = (bool)$throwExceptions;
        return $this;
    }

    /**
     * Set the HTTP client.
     *
     * @param HttpClient $client The HTTP client to use
     * @return self Provides a fluent interface
     *
     * @internal
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * @return HttpClient Returns the HTTP client to use
     */
    protected function getHttpClient()
    {
        // @codeCoverageIgnoreStart
        if ($this->httpClient === null) {
            $this->httpClient = new HttpClient();
        }
        // @codeCoverageIgnoreEnd

        return $this->httpClient;
    }
}
