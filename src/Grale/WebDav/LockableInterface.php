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
 *
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
interface LockableInterface
{
    /**
     * @param string $type  The lock type
     * @param string $scope The scope of the lock
     *
     * @return bool Returns true if a lock applies to the resource or false otherwise
     */
    public function hasLock($type = null, $scope = null);

    /**
     * @param string $type  The lock type
     * @param string $scope The scope of the lock
     *
     * @return array Returns an array of all locks applied to the resource. The array is empty
     * if there are no locks applied to the resource.
     */
    public function getLocks($type = null, $scope = null);

    /**
     * @param string $lockToken The lock token
     * @return bool
     */
    public function hasLockToken($lockToken);

    /**
     * @param string $lockToken The lock token
     * @return Lock
     */
    public function getLock($lockToken);
}
