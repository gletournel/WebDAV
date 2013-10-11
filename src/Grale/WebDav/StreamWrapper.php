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

use Grale\WebDav\Exception\HttpException;
use Grale\WebDav\Exception\StreamException;
use Grale\WebDav\Exception\AccessDeniedException;
use Grale\WebDav\Exception\NoSuchResourceException;
use Guzzle\Http\EntityBody;
use Guzzle\Stream\PhpStreamRequestFactory;

/**
 * Stream wrapper
 *
 * @todo get the base Url from the DAV client
 *
 * @author Geoffroy Letournel <geoffroy.letournel@gmail.com>
 */
class StreamWrapper
{
    /**
     * The wrapper name
     */
    const PROTOCOL = 'webdav';

    /**
     * @var resource Stream context
     *
     * @internal
     */
    public $context;

    /**
     * @var string Mode the stream was opened with
     */
    protected $mode;

    /**
     * Underlying stream resource
     *
     * @var EntityBody
     */
    protected $stream;

    /**
     * An iterator used to iterate the <tt>response</tt> elements of a WebDAV multi-status response.
     *
     * This iterator is used with opendir() and subsequent readdir() calls.
     *
     * @var array
     */
    protected $iterator;

    /**
     * @var string
     */
    protected $target;

    /**
     * @var string
     */
    protected $openedPath;

    /**
     * @var string Lock token
     */
    protected $locktoken;

    /**
     * @var array File status information cache
     */
    protected static $statCache;

    /**
     * @var Client WebDAV client used to send requests
     */
    protected static $client;

    /**
     * @param Client $client WebDAV Client to use with the stream wrapper
     *
     * @return bool Returns true on success or false on failure
     * @throws \RuntimeException If a stream wrapper has already been registered
     */
    public static function register(Client $client)
    {
        if (in_array(self::PROTOCOL, stream_get_wrappers())) {
            throw new \RuntimeException("A stream wrapper already exists for the '" . self::PROTOCOL . "' protocol.");
        }

        if ($result = stream_wrapper_register(self::PROTOCOL, __CLASS__, STREAM_IS_URL)) {
            self::$client = $client;
        }

        return $result;
    }

    /**
     * @return bool Returns true on success or false on failure
     */
    public static function unregister()
    {
        return stream_wrapper_unregister(self::PROTOCOL);
    }

    // @codingStandardsIgnoreStart

    /**
     * @param string $path       The URL of the file/resource to be opened
     * @param string $mode       The mode used to open the file, as detailed for fopen()
     * @param int    $options    Holds additional flags set by the streams API
     * @param string $openedPath Should be set to the full path of the file/resource that was actually opened
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.stream-open.php
     *
     * @internal
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        $this->parseUrl($path);

        $this->target = $this->getTarget($path);

        // We don't care about text-mode translation and binary-mode flags
        $this->mode = $mode = rtrim($mode, 'tb');

        // TODO: Remember to check if the mode is valid for the path requested!!

        $quiet = ($options & STREAM_REPORT_ERRORS) != STREAM_REPORT_ERRORS;

        if (strpos($mode, '+')) {
            return $this->triggerError('The WebDAV stream wrapper does not allow simultaneous reading and writing.', $quiet);
        }

        if (!in_array($mode, array('r', 'w', 'a', 'x'))) {
            return $this->triggerError("Mode not supported: {$mode}. Use one 'r', 'w', 'a', or 'x'.", $quiet);
        }

        // When using mode 'x', validate if the file exists before attempting to read
        if ($mode == 'x' && self::$client->exists($this->getTarget($path))) {
            return $this->triggerError("{$path} already exists", $quiet);
        }

        if ($mode == 'r') {
            $result = $this->openReadOnly($path);
        } elseif ($mode == 'a') {
            $result = $this->openAppendMode($path);
        } else {
            $result = $this->openWriteOnly($path);
        }

        if ($result && (bool)($options & STREAM_USE_PATH)) {
            $openedPath = $path;
        }

        return $result;
    }

    /**
     * @param int $bytes Amount of bytes to read from the underlying stream
     *
     * @return string If there are less than count bytes available, return as many as are available.
     * If no more data is available, return either false or an empty string.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-read.php
     *
     * @internal
     */
    public function stream_read($bytes)
    {
        return $this->stream->read($bytes);
    }

    /**
     * @param string $data Data to write to the underlying stream
     *
     * @return int Returns the number of bytes written to the stream, or 0 if none could be written
     * @link http://www.php.net/manual/en/streamwrapper.stream-write.php
     *
     * @internal
     */
    public function stream_write($data)
    {
        return $this->stream->write($data);
    }

    /**
     * @return bool Should return true if the cached data was successfully stored (or if there was no data to store),
     * or false if the data could not be stored.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-flush.php
     *
     * @internal
     */
    public function stream_flush()
    {
        if ($this->mode == 'r') {
            return false;
        }

        $this->stream->rewind();

        try {
            $headers = array();

            if (false /* $this->locktoken */) {
                $headers['If'] = sprintf('(<%s>)', '' /* $this->locktoken */);
            }

            self::$client->put($this->target, $headers, $this->stream);

        } catch (HttpException $e) {
            return $this->triggerError($e->getMessage());
        }

        return true;
    }

    /**
     * @return bool Should return true if the read/write position is at the end of the stream
     * and if no more data is available to be read, or false otherwise
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-eof.php
     *
     * @internal
     */
    public function stream_eof()
    {
        return $this->stream->feof();
    }

    /**
     * @return int Returns the current position in the stream
     * @link http://www.php.net/manual/en/streamwrapper.stream-tell.php
     *
     * @internal
     */
    public function stream_tell()
    {
        return $this->stream->ftell();
    }

    /**
     * @param int $offset The stream offset to seek to
     * @param int $whence Whence (SEEK_SET, SEEK_CUR, SEEK_END)
     *
     * @return bool Return true if the position was updated, false otherwise
     * @link http://www.php.net/manual/en/streamwrapper.stream-seek.php
     *
     * @internal
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return $this->stream->seek($offset, $whence);
    }

    /**
     * @param int $operation LOCK_SH, LOCK_EX or LOCK_UN
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.stream-lock.php
     *
     * @internal
     */
    public function stream_lock($operation)
    {
        // We don't care about LOCK_NB
        $operation = $operation & ~LOCK_NB;

        $result = false;

        if ($operation == LOCK_UN) {
            $result = $this->releaseLock();
        } else {
            $result = $this->lock($operation == LOCK_SH ? 'shared' : 'exclusive');
        }

        return $result;
    }

    /**
     * @param int $cast STREAM_CAST_FOR_SELECT or STREAM_CAST_AS_STREAM
     *
     * @return resource Should return the underlying stream resource used by the wrapper, or false
     * @link http://www.php.net/manual/en/streamwrapper.stream-cast.php
     *
     * @internal
     */
    public function stream_cast($cast)
    {
        return $this->stream->getStream();
    }

    /**
     * @return array Returns an array of file stat data
     * @link http://www.php.net/manual/en/streamwrapper.stream-stat.php
     *
     * @internal
     */
    public function stream_stat()
    {
        $stats = fstat($this->stream->getStream());

        if ($this->mode == 'r' && $this->stream->getSize()) {
            $stats[7] = $stats['size'] = $this->stream->getSize();
        }

        return $stats;
    }

    /**
     * All resources that were locked, or allocated, by the wrapper should be released
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-open.php
     *
     * @internal
     */
    public function stream_close()
    {
        if ($this->locktoken) {
            $this->releaseLock();
        }

        $this->stream = null;
        $this->mode   = null;
    }

    /**
     * @param string $old
     * @param string $new
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.rename.php
     *
     * @internal
     */
    public function rename($old, $new)
    {
        echo PHP_EOL . "rename('$old', '$new')";

        // Can retrieve the context options... from rename()
        $contextOptions = $this->getOptions();

        // Request
        // -------
        // MOVE {$old} HTTP/1.1
        // Destination: {$new}
        // If: (<locktoken>)

        // Response
        // --------
        // HTTP Status-Code 201 or 204 == true
        // false otherwise

        // Errors
        // ------
        // No such file or directory

    }

    /**
     * @param string $path The path to the file to be deleted
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.unlink.php
     *
     * @internal
     */
    public function unlink($path)
    {
        $this->parseUrl($path);

        // Can retrieve the context options... from unlink()
        $contextOptions = $this->getOptions();

        try {
            self::$client->createLock($this->getTarget($path), 'exclusive', array(
                'owner' => 'test'
            ));

            echo self::$client->getLastRequest();

            self::$client->delete($this->getTarget($path), $this->locktoken);
        } catch (\Exception $e) {
            return $this->triggerError($e->getMessage());
        }

        return true;
    }

    /**
     * @param string $path    The path to the directory to create
     * @param int    $mode    Permission flags. See mkdir().
     * @param int    $options A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.mkdir.php
     *
     * @internal
     */
    public function mkdir($path, $mode, $options)
    {
        fprintf(STDOUT, "   mkdir('$path', %o, $options)\n", $mode);

        $mode = $mode === null ? 0777 : $mode;

        $this->parseUrl($path);

        // Can retrieve the context options... from mkdir()
        $contextOptions = $this->getOptions();

        if ($options & STREAM_MKDIR_RECURSIVE) {
        }

        $headers = array();

        if ($this->locktoken) {
            $headers['If'] = sprintf('(<%s>)', $this->locktoken);
        }

        // Request
        // -------
        // MKCOL {$path} HTTP/1.1
        // If: (<{$locktoken}>)

        // Response
        // --------
        // HTTP Status-Code 201 == true
        // false otherwise
    }

    /**
     * @param string $path    The path to the directory which should be removed
     * @param int    $options A bitwise mask of values, such as STREAM_MKDIR_RECURSIVE
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.rmdir.php
     *
     * @internal
     */
    public function rmdir($path, $options)
    {
        echo PHP_EOL . "rmdir('$path', $options)";

        $this->parseUrl($path); // resolvePath()

        // Can retrieve the context options... from rmdir()
        $contextOptions = $this->getOptions();

        $headers = array();

        if ($this->locktoken) {
            $headers['If'] = sprintf('(<%s>)', $this->locktoken);
        }

        // The directory must be empty, and the relevant permissions must permit this
        // see $options & STREAM_MKDIR_RECURSIVE

        // Request
        // -------
        // DELETE {$path} HTTP/1.1
        // If: (<{$locktoken}>)

        // Response
        // --------
        // HTTP Status-Code 204 == true
        // false otherwise
    }

    /**
     * @param string $path    The path to the directory (e.g. "webdav://dir")
     * @param int    $options Whether or not to enforce safe_mode. Deprecated.
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.dir-opendir.php
     *
     * @internal
     */
    public function dir_opendir($path, $options)
    {
        $this->parseUrl($path);

        // Reset the cache
        $this->clearStatCache();

        $this->openedPath = $path;

        try {
            $response = self::$client->propfind($this->getTarget(), null, 1);

            $this->iterator = $response->getIterator();

        } catch (NoSuchResourceException $e) {
            return $this->triggerError("No such file or directory: {$path}");
        } catch (HttpException $e) {
            return $this->triggerError($e->getMessage());
        }

        // Skip the first entry of the PROPFIND request
        $this->iterator->next();

        return true;
    }

    /**
     * @return string Should return a string representing the next filename, or false if there is no next file
     * @link http://www.php.net/manual/en/streamwrapper.dir-readdir.php
     *
     * @internal
     */
    public function dir_readdir()
    {
        $result = false;

        if ($this->iterator->valid()) {
            $resource = $this->iterator->current()->getResource();
            $result   = $resource->getFilename();

            self::$statCache[$this->getRealPath($resource->getHref())] = $resource->getStat();

            $this->iterator->next();
        }

        return $result;
    }

    /**
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.dir-rewinddir.php
     *
     * @internal
     */
    public function dir_rewinddir()
    {
        $this->clearStatCache();

        $this->iterator->rewind();

        // skip the first entry of the PROPFIND request
        $this->iterator->next();

        return true;
    }

    /**
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.dir-closedir.php
     *
     * @internal
     */
    public function dir_closedir()
    {
        $this->iterator = null;

        return true;
    }

    /**
     * @param string $path  The path to the file or directory (e.g. "webdav://path/to/file")
     * @param int    $flags Holds additional flags set by the streams API
     *
     * @return array Returns an array of file stat data
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @internal
     */
    public function url_stat($path, $flags)
    {
        $this->parseUrl($path);

        if (isset(self::$statCache[$path])) {
            return self::$statCache[$path];
        }

        $quiet = ($flags & STREAM_URL_STAT_QUIET) == STREAM_URL_STAT_QUIET;

        // self::$client->exists($uri); // HTTP HEAD request

        try {
            $response = self::$client->propfind($this->getTarget($path));

            if ($response->count() > 0) {
                $result = current($response->getIterator());
                $resource = $result->getResource();

                return $resource->getStat();
            }

        } catch (NoSuchResourceException $e) {
            return $this->triggerError("File or directory not found: {$path}", $quiet);
        } catch (HttpException $e) {
            return $this->triggerError($e->getMessage(), $quiet);
        }

        return false;
    }

    // @codingStandardsIgnoreEnd

    /**
     * @param string $path
     */
    protected function parseUrl($path)
    {
    }

    /**
     * @param string $path
     * @return string
     */
    protected function getTarget($path = null)
    {
        if ($path === null) {
            $path = $this->openedPath;
        }

        list($scheme, $target) = explode('://', $path, 2);

        // Remove erroneous leading or trailing slashes
        return trim($target, '/');
    }

    /**
     * @param string $uri
     * @return string
     */
    protected function getRealPath($uri)
    {
        $baseUrl  = '/webdav';
        $realPath = str_replace($baseUrl, null, $uri);

        return self::PROTOCOL . '://' . trim($realPath, '/');
    }

    /**
     * Get the stream context options available to the current stream
     *
     * @return array Returns an array of options
     */
    protected function getOptions()
    {
        $context = $this->context ?: stream_context_get_default();
        $options = stream_context_get_options($context);

        return isset($options[self::PROTOCOL]) ? $options[self::PROTOCOL] : array();
    }

    /**
     * Get a specific stream context option
     *
     * @param string $name Name of the option to retrieve
     *
     * @return mixed
     */
    protected function getOption($name)
    {
        $options = $this->getOptions();

        return isset($options[$name]) ? $options[$name] : null;
    }

    /**
     * @param string $scope
     *
     * @return bool Returns true on success or false on failure
     */
    protected function lock($scope)
    {
        /* Pre-Request:
         *
         *   OPTIONS {$path} HTTP/1.1
         *
         * Response:
         *
         *   Dav: 1, 3
         *   Allow: GET, POST, etc.
         *
         * if (!($this->compliance & Compliance::CLASS2)) {
         *     return false;
         * }
         */

        $result  = null;
        $timeout = 3600;

        if ($this->locktoken === null) {
            $result = self::$client->createLock('path', $scope, array(
                'timeout' => $timeout,
                'owner'   => 'john'
            ));
        } else {
            $result = self::$client->refreshLock('path', $this->locktoken, $timeout);
        }

        if ($result !== null) {
            $this->locktoken = $result->getToken();
        }

        return $result !== null;
    }

    /**
     * @return bool Returns true on success or false on failure
     */
    protected function releaseLock()
    {
        $result = self::$client->releaseLock('path', $this->locktoken);

        $this->locktoken = null;

        return $result;
    }

    /**
     * Initialize the stream wrapper for a read-only stream
     *
     * - place the file pointer at the beginning of the file
     *
     * @return bool Returns true on success or false on failure
     */
    protected function openReadOnly($path)
    {
        $target  = $this->getTarget($path);
        $request = self::$client->getHttpClient()->get($target);
        $factory = new PhpStreamRequestFactory();

        $this->stream = $factory->fromRequest($request, array(), array('stream_class' => 'Guzzle\Http\EntityBody'));

        return true;
    }

    /**
     * Initialize the stream wrapper for an append stream
     *
     * @return bool Returns true on success or false on failure
     */
    protected function openAppendMode($path)
    {
        try {
            $target   = $this->getTarget($path);
            $response = self::$client->get($target);

            $this->stream = EntityBody::fromString($response->getBody());
            $this->stream->seek(0, SEEK_END);
        } catch (HttpException $e) {
            // The resource does not exist, so use a simple write stream
            return $this->openWriteOnly($path);
        }

        return true;
    }

    /**
     * Initialize the stream wrapper for a write-only stream
     *
     * @return bool Returns true on success or false on failure
     */
    protected function openWriteOnly($path)
    {
        $this->stream = new EntityBody(fopen('php://temp', 'r+'));

        return true;
    }

    /**
     * Clear the file status cache
     *
     * @param string $path If a path is specific, clearstatcache() will be called
     */
    protected function clearStatCache($path = null)
    {
        self::$statCache = array();

        if ($path !== null) {
            clearstatcache(true, $path);
        }
    }

    /**
     * Trigger an error
     *
     * @param string $error Error message for the error to trigger
     * @param int    $quiet If set to true, then no error or exception occurs
     *
     * @return bool Returns false
     * @throws StreamException if throw_exceptions is true
     */
    protected function triggerError($error, $quiet = false)
    {
        if (!$quiet) {
            if ($this->getOption('throw_exceptions')) {
                throw new StreamException($error);
            } else {
                trigger_error($error, E_USER_WARNING);
            }
        }

        return false;
    }
}
