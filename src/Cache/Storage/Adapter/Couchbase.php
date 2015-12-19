<?php

namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use Zend\Cache\Exception;
use Zend\Cache\Storage\Adapter\AbstractAdapter;
use Zend\Cache\Storage\Capabilities;
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
        $expiry = $this->getOptions()->getTtl();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $result = true;

        try {
            $this->resourceManager->getResource($this->resourceId)->remove($internalKey);
        } catch (\CouchbaseException $e) {

        }

        try {
            $this->resourceManager->getResource($this->resourceId)->insert($internalKey, $value, ['expiry' => $expiry]);
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
     * Internal method to set an item only if token matches
     *
     * @param  mixed  $token
     * @param  string $normalizedKey
     * @param  mixed  $value
     * @return bool
     * @throws Exception\ExceptionInterface
     * @see    getItem()
     * @see    setItem()
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $result = true;

        $memc = $this->getCouchbaseResource();
        // $expiration = $this->expirationTime();
        try {
            $memc->replace($this->namespacePrefix . $normalizedKey, $value);
        } catch (\CouchbaseException $e) {
            $result = false;
        }

        return $result;
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

    /**
     * Internal method to get capabilities of this adapter
     *
     * @return Capabilities
     */
    protected function internalGetCapabilities()
    {
        if ($this->capabilities === null) {
            $this->capabilityMarker = new \stdClass();
            $this->capabilities     = new Capabilities(
                $this,
                $this->capabilityMarker,
                [
                    'supportedDatatypes' => [
                        'NULL'     => true,
                        'boolean'  => true,
                        'integer'  => true,
                        'double'   => true,
                        'string'   => true,
                        'array'    => true,
                        'object'   => 'array',
                        'resource' => false,
                    ],
                    'supportedMetadata'  => [],
                    'minTtl'             => 1,
                    'maxTtl'             => 0,
                    'staticTtl'          => true,
                    'ttlPrecision'       => 1,
                    'useRequestTime'     => false,
                    'expiredRead'        => false,
                    'maxKeyLength'       => 255,
                    'namespaceIsPrefix'  => true,
                ]
            );
        }

        return $this->capabilities;
    }
}
