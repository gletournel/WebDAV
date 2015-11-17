<?php
/**
 * This file is part of the WebDav package.
 *
 * (c) Geoffroy Letournel <geoffroy.letournel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Grale\WebDav\Header;

use Grale\WebDav\Header\TimeoutValue\TimeoutValueFactory;
use Grale\WebDav\Header\TimeoutValue\TimeoutValueInterface;

/**
 * A <tt>Timeout</tt> header
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class TimeoutHeader
{

    /**
     * Infinite timeout
     */
    const INFINITE = - 1;

    /**
     *
     * @var int Number of seconds
     */
    protected $seconds;

    /**
     *
     * @var TimeoutValueInterface
     */
    private $value;

    /**
     *
     * @param int $timeout
     *            Timeout in seconds
     */
    public function __construct($timeout)
    {
        $value = TimeoutValueFactory::createFromValue($timeout);
        $this->value = $value;
        $this->seconds = (string) $timeout;
    }

    /**
     * Get the timeout in seconds.
     *
     * @return int Returns the timeout in seconds
     */
    public function getSeconds()
    {
        return $this->seconds;
    }

    /**
     * Get the validity date.
     *
     * @param int $time
     *            Timestamp when the validity date must be calculated from (Defaults to current timestamp)
     *            
     * @return int Returns the validity date as a UNIX timestamp
     */
    public function getValidity($time = null)
    {
        // @codeCoverageIgnoreStart
        if ($time === null) {
            $time = time();
        }
        // @codeCoverageIgnoreEnd
        
        return $this->seconds >= 0 ? $this->value->getValidity($time) : self::INFINITE;
    }

    /**
     * Get a string representation of the timeout value.
     *
     * An infinite timeout will be returned as the "Infinite" string while a timeout of 1 hour,
     * for instance, will be returned as follows: "Second-3600".
     *
     * @return string Returns the timeout value as a string
     */
    public function __toString()
    {
        $header = null;
        
        if (! $this->value->isInfinite()) {
            $header = sprintf('Second-%s', $this->value->__toString());
        } else {
            $header = 'Infinite';
        }
        
        return $header;
    }

    /**
     * Get the infinite timeout.
     *
     * @return self Returns the infinite timeout
     */
    public static function getInfinite()
    {
        return new static(self::INFINITE);
    }

    /**
     * Parses the string provided as a timeout.
     *
     * @param string $str
     *            Timeout as a string
     * @return self Returns the parsed timeout
     */
    public static function parse($str)
    {
        $header = null;
        
        if (strtolower($str) == 'infinite') {
            $header = self::getInfinite();
        } elseif (strtolower(substr($str, 0, 7)) == 'second-' && ctype_digit(substr($str, 7))) {
            $header = new static(substr($str, 7));
        } elseif (is_numeric($str)) {
            $header = new static($str);
        }
        
        return $header;
    }
}
