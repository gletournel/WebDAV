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
use Guzzle\Http\Url;

/**
 * Stream wrapper
 *
 * The following context options are available:
 *
 * - <tt>base_url</tt>
 * - <tt>user_agent</tt>
 * - <tt>curl_options</tt>
 * - <tt>throw_exceptions</tt>
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
     * The wrapper name for WebDAV over HTTPS
     */
    const PROTOCOL_SECURE = 'webdavs';

    /**
     * @var resource Stream context
     * @internal
     */
    public $context;

    /**
     * @var string Mode the stream was opened with
     */
    protected $mode;

    /**
     * @var EntityBody Underlying stream resource
     */
    protected $stream;

    /**
     * @var Iterator An iterator used to iterate the <tt>response</tt> elements of a WebDAV multi-status response
     */
    protected $iterator;

    /**
     * @var string The file/resource that was actually opened
     */
    protected $openedPath;

    /**
     * @var string Lock token
     */
    protected $locktoken;

    /**
     * @var Client WebDAV client used to send requests
     */
    protected static $client;

    /**
     * @var array Default stream context options
     */
    protected static $options = array();

    /**
     * @var array Wrappers and their respective schemes
     */
    protected static $protocols = array(
        self::PROTOCOL        => 'http',
        self::PROTOCOL_SECURE => 'https'
    );

    /**
     * @var array File status information cache
     */
    protected static $statCache;

    /**
     * @param array  $options An associative array of default stream context options
     * @param Client $client  WebDAV Client to use with the stream wrapper
     *
     * @return bool Returns true on success or false on failure
     * @throws \RuntimeException If a stream wrapper has already been registered
     */
    public static function register(array $options = null, Client $client = null)
    {
        $result = true;

        foreach (array_keys(self::$protocols) as $protocol) {
            if (in_array($protocol, stream_get_wrappers())) {
                throw new \RuntimeException("A stream wrapper already exists for the '$protocol' protocol.");
            }

            $result = $result && stream_wrapper_register($protocol, __CLASS__, STREAM_IS_URL);
        }

        if ($result) {
            // @codeCoverageIgnoreStart
            if ($client === null) {
                $client = self::getDefaultClient();
            }
            // @codeCoverageIgnoreEnd

            self::$options = $options ? array(self::PROTOCOL => $options) : array();
            self::$client  = $client;

            stream_context_set_default(self::$options);
        }

        return $result;
    }

    /**
     * @return bool Returns true on success or false on failure
     */
    public static function unregister()
    {
        $result = true;

        foreach (array_keys(self::$protocols) as $protocol) {
            if (in_array($protocol, stream_get_wrappers())) {
                $result = $result && stream_wrapper_unregister($protocol);
            }
        }

        return $result;
    }

    /**
     * @return Client Returns the default WebDAV Client to use with the stream wrapper
     */
    public static function getDefaultClient()
    {
        return new Client();
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
        $result = false;

        try {
            $url = $this->resolveUrl($path);

            // We don't care about text-mode translation and binary-mode flags
            $this->mode = $mode = rtrim($mode, 'tb');

            // TODO: Remember to check if the mode is valid for the path requested!!

            if (strpos($mode, '+')) {
                throw new \RuntimeException('WebDAV stream wrapper does not allow simultaneous reading and writing.');
            }

            if (!in_array($mode, array('r', 'w', 'a', 'x'))) {
                throw new \RuntimeException("Mode not supported: {$mode}. Use one 'r', 'w', 'a', or 'x'.");
            }

            // Can retrieve the context options
            self::$client->setConfig($this->getOptions());

            // When using mode 'x', validate if the file exists before attempting to read
            if ($mode == 'x' && self::$client->exists($url)) {
                throw new \RuntimeException("{$path} already exists");
            }

            if ($mode == 'r') {
                $result = $this->openReadOnly($url);
            } elseif ($mode == 'a') {
                $result = $this->openAppendMode($url);
            } else {
                $result = $this->openWriteOnly($url);
            }

        } catch (\Exception $exception) {
            $result = $this->triggerError($exception, ($options & STREAM_REPORT_ERRORS) != STREAM_REPORT_ERRORS);
        }

        if ($result) {
            $this->openedPath = (string)$url;

            if ((bool)($options & STREAM_USE_PATH)) {
                $openedPath = $this->openedPath;
            }
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
        $data = '';

        // Simultaneous reading and writing is not supported
        if ($this->mode == 'r') {
            $data = $this->stream->read($bytes);
        }

        return $data;
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
        $bytes = 0;

        // Simultaneous reading and writing is not supported
        if ($this->mode != 'r') {
            $bytes = $this->stream->write($data);
        }

        return $bytes;
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

        $options = array();

        if ($this->locktoken) {
            $options['locktoken'] = sprintf('(<%s>)', $this->locktoken);
        }

        return self::$client->put($this->openedPath, $this->stream, $options);
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
        $result = false;

        try {
            // We don't care about LOCK_NB
            $operation = $operation & ~LOCK_NB;

            if ($operation == LOCK_UN) {
                $result = $this->releaseLock();
            } else {
                $result = $this->lock($operation == LOCK_SH ? Lock::SHARED : Lock::EXCLUSIVE);
            }
        } catch (\Exception $exception) {
            $result = $this->triggerError($exception);
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
        $result = false;

        try {
            $source      = $this->resolveUrl($old);
            $destination = $this->resolveUrl($new);

            // Can retrieve the context options
            self::$client->setConfig($this->getOptions());

            $result = self::$client->move($source, $destination, array('locktoken' => $this->locktoken));

        } catch (\Exception $exception) {
            $result = $this->triggerError($exception);
        }

        return $result;
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
        $result = false;

        try {
            $url = $this->resolveUrl($path);

            // Can retrieve the context options
            self::$client->setConfig($this->getOptions());

            $options = array();

            if ($this->locktoken) {
                $options['locktoken'] = $this->locktoken;
            }

            $result = self::$client->delete($url, $options);

        } catch (\Exception $exception) {
            $result = $this->triggerError($exception);
        }

        return $result;
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
        $result = false;

        try {
            if ($options & STREAM_MKDIR_RECURSIVE) {
                throw new \RuntimeException('WebDAV stream wrapper does not allow to create directories recursively');
            }

            $url = $this->resolveUrl($path);

            // Can retrieve the context options
            self::$client->setConfig($this->getOptions());

            $options = array();

            if ($this->locktoken) {
                $options['locktoken'] = $this->locktoken;
            }

            $result = self::$client->mkcol($url, $options);

        } catch (\Exception $exception) {
            $result = $this->triggerError($exception, ($options & STREAM_REPORT_ERRORS) != STREAM_REPORT_ERRORS);
        }

        return $result;
    }

    /**
     * @param string $path    The path to the directory which should be removed
     * @param int    $options A bitwise mask of values, such as STREAM_REPORT_ERRORS
     *
     * @return bool Returns true on success or false on failure
     * @link http://www.php.net/manual/en/streamwrapper.rmdir.php
     *
     * @internal
     */
    public function rmdir($path, $options)
    {
        $result = false;

        try {
            $url = $this->resolveUrl($path);

            // Can retrieve the context options
            self::$client->setConfig($this->getOptions());

            $options = array();

            if ($this->locktoken) {
                $options['locktoken'] = $this->locktoken;
            }

            // The directory must be empty, and the relevant permissions must permit this
            $result = self::$client->delete($url, $options);

        } catch (\Exception $exception) {
            $result = $this->triggerError($exception, ($options & STREAM_REPORT_ERRORS) != STREAM_REPORT_ERRORS);
        }

        return $result;
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
        $result = true;

        try {
            $url = $this->resolveUrl($path);

            // Reset the cache
            $this->clearStatCache();

            // Can retrieve the context options
            self::$client->setConfig($this->getOptions());

            $response = self::$client->propfind($url, array('depth' => 1));

            $this->iterator   = $response->getIterator();
            $this->openedPath = $url;

        } catch (\Exception $exception) {
            $result = $this->triggerError($exception);
        }

        // Skip the first entry of the PROPFIND request
        $this->iterator->next();

        return $result;
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

            $url = (string)$this->openedPath->combine($resource->getHref());
            self::$statCache[$url] = $resource->getStat();

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
        $quiet = ($flags & STREAM_URL_STAT_QUIET) == STREAM_URL_STAT_QUIET;

        try {
            $url = $this->resolveUrl($path);

            if (isset(self::$statCache[(string)$url])) {
                return self::$statCache[(string)$url];
            }

            $response = self::$client->propfind($url, array('depth' => 0));

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
     * Get the stream context options available to the current stream
     *
     * @return array Returns an array of options
     */
    protected function getOptions()
    {
        $context = $this->context ?: stream_context_get_default(self::$options);
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
            $result = self::$client->createLock($this->openedPath, array(
                'owner'   => __CLASS__,
                'scope'   => $scope,
                'timeout' => $timeout
            ));
        } else {
            $result = self::$client->refreshLock($this->openedPath, $this->locktoken, $timeout);
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
        $result = self::$client->releaseLock($this->openedPath, $this->locktoken);

        $this->locktoken = null;

        return $result;
    }

    /**
     * Initialize the stream wrapper for a read-only stream
     *
     * @return bool Returns true on success or false on failure
     */
    protected function openReadOnly($url)
    {
        $this->stream = self::$client->getStream($url);

        return true;
    }

    /**
     * Initialize the stream wrapper for an append stream
     *
     * @return bool Returns true on success or false on failure
     */
    protected function openAppendMode($url)
    {
        try {
            $contents = self::$client->get($url);

            $this->stream = EntityBody::fromString($contents);
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
    protected function openWriteOnly($url)
    {
        $this->stream = new EntityBody(fopen('php://temp', 'r+'));

        return true;
    }

    /**
     * @param string $path
     *
     * @return Url
     * @throws \InvalidArgumentException
     */
    protected function resolveUrl($path)
    {
        $url = Url::factory($path);

        $protocol = $url->getScheme();

        if (isset(self::$protocols[$protocol])) {
            $url->setScheme(self::$protocols[$protocol]);
        }

        return $url;
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
     * @param \Exception|string $error Error to trigger
     * @param int               $quiet If set to true, then no error or exception occurs
     *
     * @return bool Returns false
     * @throws StreamException if throw_exceptions is true
     */
    protected function triggerError($error, $quiet = false)
    {
        if (!$quiet) {
            $message  = $error instanceof \Exception ? $error->getMessage() : $error;
            $previous = $error instanceof \Exception ? $error : null;

            if ($this->getOption('throw_exceptions')) {
                throw new StreamException($message, null, $previous);
            } else {
                trigger_error($message, E_USER_WARNING);
            }
        }

        return false;
    }
}
