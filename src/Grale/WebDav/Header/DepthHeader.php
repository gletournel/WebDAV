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
class DepthHeader
{
    const INFINITY = -1;

    /**
     * @param string $str
     * @return int
     */
    public static function parse($str)
    {
        return $str == 'Infinity' ? self::INFINITY : (int)$str;
    }
}
