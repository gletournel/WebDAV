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

/**
 * A multi-status response that contains multiple response messages
 *
 * This class represents a <tt>multistatus</tt> response element as defined in
 * {@link http://www.ietf.org/rfc/rfc2518.txt RFC-2518}.
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class MultiStatus implements \IteratorAggregate, \Countable
{
    /**
     * Element name as described in the WebDAV XML elements definition
     */
    const TAGNAME = 'multistatus';

    /**
     * @var string An optional response description
     */
    protected $description;

    /**
     * @var array Response messages
     */
    protected $responses = array();

    /**
     * @param array  $responses   Response messages
     * @param string $description An optional response description
     */
    public function __construct(array $responses = array(), $description = null)
    {
        foreach ($responses as $response) {
            $this->add($response);
        }

        $this->description = $description;
    }

    /**
     * @param Response $response The single response message
     * @return self Provides a fluent interface
     */
    public function add(Response $response)
    {
        $this->responses[$response->getHref()] = $response;
        return $this;
    }

    /**
     * @return string Returns information suitable to be displayed to the user explaining
     * the nature of the response. This description may be NULL.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return \Iterator Returns an iterator to be used in loop functions
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->responses);
    }

    /**
     * @return int Returns the number of single response messages
     */
    public function count()
    {
        return count($this->responses);
    }

    /**
     * @param Client $client
     * @param string $xml The multi-status response as an XML string
     *
     * @throws \RuntimeException
     * @return self Returns the parsed multi-status response as an object
     *
     * @todo
     * - validate the XML document using a WebDAV DTD
     * - register namespaces automatically with the Xpath object
     * - testing DOMDocument::loadXML throwing DOMException with the libxml settings and an erroneous XML document
     */
    public static function parse(Client $client, $xml)
    {
        $xml = preg_replace('/\s*[\r\n]\s*/', null, $xml);

        $dom = new \DOMDocument();

        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        $dom->loadXML($xml, LIBXML_NOWARNING|LIBXML_NOERROR);

        // XSD validation ? Namespaces ?

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('D', 'DAV:');

        if (!$dom->hasChildNodes()) {
            throw new \RuntimeException();
        }

        if ($dom->documentElement->localName != self::TAGNAME) {
            throw new \RuntimeException();
        }

        $result      = array();
        $description = $xpath->evaluate('string(D:responsedescription)', $dom->documentElement);

        foreach ($xpath->query('./D:response', $dom->documentElement) as $xResponse) {
            $href                = $xpath->evaluate('string(D:href)', $xResponse);
            $statusLine          = $xpath->evaluate('string(D:status)', $xResponse);
            $responseDescription = $xpath->evaluate('string(D:responsedescription)', $xResponse);

            // Determines whether this response uses a combination of href+status elements
            // or if it uses a list of propstat elements
            $responseStatus = $statusLine ? self::parseHttpStatus($statusLine) : array();

            foreach ($xpath->query('./D:propstat', $xResponse) as $xPropstat) {
                $statusCode = self::parseHttpStatus($xpath->evaluate('string(D:status)', $xPropstat));

                $responseStatus[$statusCode] = array();

                foreach ($xpath->query('./D:prop/*', $xPropstat) as $xProperty) {
                    if (!isset($client->xmlNamespaces[$xProperty->namespaceURI])) {
                        continue;
                    }

                    if (isset($client->propertyMap[$xProperty->localName])) {
                        $propertyClass = $client->propertyMap[$xProperty->localName];
                    } else {
                        $propertyClass = __NAMESPACE__ . '\\Property';
                    }

                    $responseStatus[$statusCode][] = $propertyClass::fromXml($xProperty, $client->xmlNamespaces);
                }
            }

            $result[] = new Response($href, $responseStatus, $responseDescription ? $responseDescription : null);
        }

        return new self($result, $description);
    }

    /**
     * @param string $statusLine
     * @throws \Exception
     * @return int
     * @todo define exception here
     */
    protected static function parseHttpStatus($statusLine)
    {
        if (empty($statusLine)) {
            throw new \Exception();
        }

        // Status-Line = HTTP-Version <SPACE> Status-Code <SPACE> Reason-Phrase
        list(,$strStatusCode,) = explode(' ', $statusLine, 3);

        $statusCode = intval($strStatusCode);

        if ($statusCode < 100 || $statusCode > 600) {
            throw new \Exception();
        }

        return $statusCode;
    }
}
