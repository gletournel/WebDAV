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

/**
 *
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class TimeoutHeader
{
    /**
     * Infinite timeout
     */
    const INFINITE = -1;

    /**
     * @var int Number of seconds
     */
    protected $seconds;

    /**
     * @param int $timeout Timeout in seconds
     */
    public function __construct($timeout)
    {
        $this->seconds = intval($timeout);
    }

    /**
     * Gets the timeout in seconds.
     * @return int Returns the timeout in seconds
     */
    public function getSeconds()
    {
        return $this->seconds;
    }

    /**
     * Gets the validity date.
     *
     * @param int $time Timestamp when the validity date must be calculated from (Defaults to current timestamp)
     *
     * @return int Returns the validity date as a UNIX timestamp
     */
    public function getValidity($time = null)
    {
        if ($time === null) {
            $time = time();
        }

        return $time + $this->seconds;
    }

    /**
     * Gets a string representation of the timeout.
     *
     * An infinite timeout will be returned as the "Infinite" string while a timeout of 1 hour,
     * for instance, will be returned as follows: "Second-3600".
     *
     * @return string Returns the timeout as a string
     */
    public function __toString()
    {
        $header = null;

        if ($this->seconds > self::INFINITE) {
            $header = sprintf('Second-%d', $this->seconds);
        } else {
            $header = 'Infinite';
        }

        return $header;
    }

    /**
     * Gets the infinite timeout.
     * @return self Returns the infinite timeout
     */
    public static function getInfinite()
    {
        return new static(self::INFINITE);
    }

    /**
     * Parses the string provided as a timeout.
     *
     * @param string $str Timeout as a string
     * @return self Returns the parsed timeout
     */
    public static function parse($str)
    {
        $header = null;

        if ($str == 'Infinite') {
            $header = self::getInfinite();
        } elseif (substr($str, 0, 7) == 'Second-' && ctype_digit(substr($str, 7))) {
            $header = new static(substr($str, 7));
        } elseif (is_numeric($str)) {
            $header = new static($str);
        }

        return $header;
    }
}
