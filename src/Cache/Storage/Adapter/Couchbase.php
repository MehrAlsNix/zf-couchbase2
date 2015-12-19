<?php

namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use Zend\Cache\Exception;
use Zend\Cache\Storage\Adapter\AbstractAdapter;
use Zend\Cache\Storage\FlushableInterface;

class Couchbase extends AbstractAdapter implements FlushableInterface
{
    /**
     * Major version of ext/couchbase
     *
     * @var null|int
     */
    protected static $extCouchbaseMajorVersion;

    /**
     * @var CouchbaseResourceManager
     */
    protected $resourceManager;

    /**
     * @var string
     */
    protected $resourceId;

    /**
     * The namespace prefix
     *
     * @var string
     */
    protected $namespacePrefix = '';

    /**
     * Has this instance be initialized
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * Constructor
     *
     * @param  null|array|\Traversable|CouchbaseOptions $options
     * @throws Exception\ExceptionInterface
     */
    public function __construct($options = null)
    {
        if (static::$extCouchbaseMajorVersion === null) {
            $v = (string) phpversion('couchbase');
            static::$extCouchbaseMajorVersion = ($v !== '') ? (int) $v[0] : 0;
        }
        if (static::$extCouchbaseMajorVersion < 1) {
            throw new Exception\ExtensionNotLoadedException('Need ext/couchbase version >= 2.0.0');
        }
        parent::__construct($options);
        // reset initialized flag on update option(s)
        $initialized = & $this->initialized;
        $this->getEventManager()->attach('option', function () use (& $initialized) {
            $initialized = false;
        });
    }

    /**
     * Internal method to get an item.
     *
     * @param  string $normalizedKey
     * @param  bool $success
     * @param  mixed $casToken
     * @return mixed Data on success, null on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $internalKey = $this->namespacePrefix . $normalizedKey;

        try {
            $result = $this->resourceManager->getResource($this->resourceId)->get($internalKey)->value;
            $success = true;
        } catch (\CouchbaseException $e) {
            $result = null;
            $success = false;
        }

        return $result;
    }

    /**
     * Internal method to store an item.
     *
     * @param  string $normalizedKey
     * @param  mixed $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $result = true;

        try {
            $this->resourceManager->getResource($this->resourceId)->remove($internalKey);
        } catch (\CouchbaseException $e) {

        }

        try {
            $this->resourceManager->getResource($this->resourceId)->insert($internalKey, $value);
        } catch (\CouchbaseException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Internal method to remove an item.
     *
     * @param  string $normalizedKey
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $result = true;

        try {
            $this->resourceManager->getResource($this->resourceId)->remove($internalKey);
        } catch (\CouchbaseException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Internal method to increment an item.
     *
     * @param  string $normalizedKey
     * @param  int    $value
     * @return int|bool The new value on success, false on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $memc        = $this->getCouchbaseResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $value       = (int) $value;

        $newValue = $memc->counter($internalKey, 1, array('initial' => $value))->value;

/*
        if ($newValue === false) {
            $rsCode = $memc->getResultCode();

            // initial value
            if ($rsCode == MemcachedResource::RES_NOTFOUND) {
                $newValue = $value;
                $memc->add($internalKey, $newValue, $this->expirationTime());
                $rsCode = $memc->getResultCode();
            }

            if ($rsCode) {
                throw $this->getExceptionByResultCode($rsCode);
            }
        }
*/
        return $newValue;
    }

    /**
     * Flush the whole storage
     *
     * @return bool
     */
    public function flush()
    {
        $this->getCouchbaseResource()->manager()->flush();

        return true;
    }

    /**
     * Initialize the internal memcached resource
     *
     * @return \CouchbaseBucket
     */
    protected function getCouchbaseResource()
    {
        if (!$this->initialized) {
            $options = $this->getOptions();

            // get resource manager and resource id
            $this->resourceManager = $options->getResourceManager();
            $this->resourceId      = $options->getResourceId();

            // init namespace prefix
            $namespace = $options->getNamespace();
            if ($namespace !== '') {
                $this->namespacePrefix = $namespace . $options->getNamespaceSeparator();
            } else {
                $this->namespacePrefix = '';
            }

            // update initialized flag
            $this->initialized = true;
        }

        return $this->resourceManager->getResource($this->resourceId);
    }

    /**
     * Set options.
     *
     * @param  array|\Traversable|CouchbaseOptions $options
     * @return \CouchbaseCluster
     * @see    getOptions()
     */
    public function setOptions($options)
    {
        if (!$options instanceof CouchbaseOptions) {
            $options = new CouchbaseOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * Get options.
     *
     * @return CouchbaseOptions
     * @see setOptions()
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new CouchbaseOptions());
        }
        return $this->options;
    }
}
