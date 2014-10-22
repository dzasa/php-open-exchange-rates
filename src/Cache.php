<?php
namespace Dzasa\OpenExchangeRates;

use Exception;
use Memcache;

/**
 * Cache wrapper for Memcache, APC and file caching method
 * 
 * @author Jasenko Rakovic <naucnik@gmail.com> 
 */
class Cache {

    private $handler;
    private $memcache;
    private $memcacheConfig = array(
        'host' => null,
        'port' => null
    );
    private $fileCacheConfig = array(
        'cache_dir' => null
    );

    function __construct($handler, $config = array()) {
        $this->handler = $handler;
        if ($handler === 'memcache') {
            $this->memcache = $config;

            if (class_exists("Memcache")) {
                try {
                    $this->memcache = new Memcache;
                    $this->memcacheConfig = $config;
                    $connection = @$this->memcache->connect($this->memcacheConfig['host'], $this->memcacheConfig['port']);

                    if (!$connection) {
                        throw new Exception("Cannot connect to Memcache server!");
                    }
                } catch (Exception $e) {
                    throw $e;
                }
            } else {
                throw new Exception("You don't have Memecache support installed!");
            }
        } else if ($handler === 'apc') {
            if (!extension_loaded('apc')) {
                throw new Exception("You don't have APC installed!");
            }
        } else if($handler === 'file'){
            $this->fileCacheConfig = $config;
            
            if(!isset($this->fileCacheConfig['cache_dir'])){
                throw new Exception("Caching directory must be defined!");
            } else if(!is_writable($this->fileCacheConfig['cache_dir'])){
                throw new Exception("Caching directory must exist and be writable!");
            }
        }
    }

    /**
     * Get cached data
     * 
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        switch ($this->handler) {
            case "memcache":
                return $this->memcache->get($key);
                break;
            case "apc":
                return apc_fetch($key);
                break;
            case "file":
                $fileContent = @file_get_contents($this->fileCacheConfig['cache_dir']."/".$key);
                
                if(!$fileContent){
                    return false;
                }
                return unserialize($fileContent);
                break;
        }
    }

    /**
     * Cache data
     * 
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value) {
        switch ($this->handler) {
            case "memcache":
                $this->memcache->set($key, $value);
                break;
            case "apc":
                apc_store($key, $value);
                break;
            case "file":
                $serializedValue = serialize($value);
                
                file_put_contents($this->fileCacheConfig['cache_dir']."/".$key, $serializedValue);
                break;
        }
    }

}
