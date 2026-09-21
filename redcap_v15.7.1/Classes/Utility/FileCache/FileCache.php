<?php
namespace Vanderbilt\REDCap\Classes\Utility\FileCache;

use Exception;
use SplFileObject;

class FileCache
{
    const VERSION = '1.0.0';
    const DEFAULT_TTL = 900;
    const FILE_EXTENSION = 'cache';

    const OPTION_NAMESPACE = 'namespace';
    const OPTION_CACHE_DIR = 'cache_dir';
    const OPTION_FILENAME_VISITOR = 'filename_visitor';

    /**
     * use a namespace for better organization
     * of cached data
     *
     * @var string
     */
    private $namespace;

    /**
     * path to the directory
     * where cache files will be stored
     *
     * @var string
     */
    private $cacheDir;

    /**
     * optional visitor function that can modify the name
     * of the cache file
     *
     * @var NameVisitorInterface
     * @return [$filename, $extension]
     */
    private $fileNameVisitor;

    public function __construct($namespace='', $cacheDir=null, $fileNameVisitor=null)
    {
        if(is_null($cacheDir) && defined('APP_PATH_TEMP')) $cacheDir = APP_PATH_TEMP;
        if(!file_exists($cacheDir) || !is_dir($cacheDir)) {
            $cacheDir = sys_get_temp_dir();
        }
        $this->namespace = $namespace;
        $this->cacheDir = realpath($cacheDir);
        $this->fileNameVisitor = $fileNameVisitor;
    }

    /**
     * get the name and path of the file
     * where the key is stored
     *
     * @param string $key
     * @return string
     */
    private function getFilePath($key)
    {
        $filename = $this->getFileName($key);
        $path = $this->cacheDir.DIRECTORY_SEPARATOR.$filename;
        return $path;
    }

    /**
     * get the normalized file name for a specific key
     *
     * @param string $key
     * @return string
     */
    public function getFileName($key) {
        $hashedFilename = sha1(self::VERSION.$this->namespace.$key);
        $extension = self::FILE_EXTENSION;
        if($this->fileNameVisitor instanceof NameVisitorInterface) {
            list($hashedFilename, $extension) = call_user_func_array([$this->fileNameVisitor, 'visit'], [$key, $hashedFilename, $extension]);
        }
        return sprintf("%s.%s", $hashedFilename, $extension);
    }

    public function delete($key)
    {
        $filename = $this->getFilePath($key);
        if(!file_exists($filename)) return;
        return @unlink($filename);
    }

    /**
     * cache a file with a specific key
     * in the current namespace.
     * overwrite existing files
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl seconds to live (default to 15 minutes)
     * @return void
     */
    function set($key, $data, $ttl=self::DEFAULT_TTL)
    {
        $mode = 'w+';
        $this->write($mode, $key, $data, $ttl);
    }

    /**
     * cache a file with a specific key
     * in the current namespace.
     * append to existing file or create it.
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl seconds to live (default to 15 minutes)
     * @return void
     */
    function append($key, $data, $ttl=self::DEFAULT_TTL)
    {
        $mode = 'a+';
        $this->write($mode, $key, $data, $ttl);
    }

    /**
     * Sets a file cache.
     * The access time is set whenever a new file is created.
     * For existing files, the access time is kept the same.
     * The access time can be used as alternative to creation time
     * to overcome the File System Tunneling phenomenon in windows.
     * 
     * @param string $mode The mode in which to open the file. 
     * @param string $key
     * @param string $data
     * @param int $ttl
     * @return int|false
     */
    private function write($mode, $key, $data, $ttl)
    {
        try {
            $filePath = $this->getFilePath($key);
            $isNewFile = !file_exists($filePath);
            if($this->isExpired($key)) {
                $this->delete($key);
                $isNewFile = true;
            }
            $file = new SplFileObject($filePath, $mode);
            if(!$file) throw new Exception("Could not open file at $filePath using mode $mode");
            $locked = $file->flock(LOCK_EX); // do an exclusive lock
            if(!$locked) throw new Exception("Could not lock file $filePath for writing");
            $totalBytes = $file->fwrite($data);
            if($totalBytes===false) throw new Exception("Could not write to cache file $filePath");
            // set lifetime
            $lifespan = time()+$ttl;
            // Use access time as an alternative for creation time to overcome file system tunneling in Windows.
            // If the file is new, use the current time. Otherwise, use the previously set access time (set during creation).
            $accessTime = $isNewFile ? time() : fileatime($filePath);
            touch($filePath, $lifespan, $accessTime); // set the modification time of the file to its lifespan
            return $totalBytes;
        } catch (\Throwable $th) {
            // throw $th;
            return false;
        }finally {
            if($file instanceof SplFileObject) $file->flock(LOCK_UN);   // release the lock
        }
    }

    /**
     * check if a key exists
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        $filePath = $this->getFileForReading($key);
        return($filePath!==false);
    }

    /**
     * get a cached file with a specific key
     * in the current namespace.
     * do not return if the value has expired
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        $filePath = $this->getFileForReading($key);
        if($filePath===false) return false;
        $data = file_get_contents($filePath);
        return $data;
    }

    public function getLine($key, $line=0)
    {
        $filePath = $this->getFileForReading($key);
        if($filePath===false) return;
        $file = new SplFileObject($filePath, $mode='r');
        $file->fseek($line);
        $line = $file->fgets();
        return $line;
    }

    /**
     * cache a file with a specific key
     * in the current namespace.
     * append a new line of data to existing file or create it.
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl seconds to live (default to 15 minutes)
     * @return void
     */
    function appendLine($key, $data, $ttl=self::DEFAULT_TTL) { return $this->append($key, $data.PHP_EOL , $ttl); }


    /**
     * Check if a cached file with a specific key has expired
     *
     * @param string $key
     * @return boolean
     */
    public function isExpired($key) {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath) || !is_readable($filePath)) return true; // File doesn't exist or isn't readable

        $ttl = $this->getSystemFileModificationTime($filePath);
        return (time() > $ttl); // True if expired, false otherwise
    }


    public function getFileForReading($key) {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath) || !is_readable($filePath)) return false;
        if ($this->isExpired($key)) {
            $this->delete($key);
            return false;
        }
        return $filePath;
    }
    

    /**
     * get the system modification time;
     * this could be used instead of storing the time
     * inside the cache
     *
     * @param string $filePath
     * @return int
     */
    private function getSystemFileModificationTime($filePath)
    {
        clearstatcache(true, $filePath);
        $modificationTime = filemtime($filePath);
        return $modificationTime;
    }

 }