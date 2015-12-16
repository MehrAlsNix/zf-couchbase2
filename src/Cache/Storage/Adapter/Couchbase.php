<?php

namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use CouchbaseCluster as CouchbaseClusterResource;
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
    /
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        return $this->resourceManager->getResource($this->resourceId)->get($normalizedKey);
    }

    /**
     * Internal method to store an item.
     *
     * @param  string $normalizedKey
     * @param  mixed $value
     * @return bool
     * @throws Exception\ExceptionInterface
     /
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $this->resourceManager->getResource($this->resourceId)->insert($normalizedKey, $value);

        return true;
    }

    /**
     * Internal method to remove an item.
     *
     * @param  string $normalizedKey
     * @return bool
     * @throws Exception\ExceptionInterface
     /
    protected function internalRemoveItem(& $normalizedKey)
    {
        $this->resourceManager->getResource($this->resourceId)->remove($normalizedKey);
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
