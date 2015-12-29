<?php
/**
 * zf-couchbase2
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @copyright 2015 MehrAlsNix (http://www.mehralsnix.de)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://github.com/MehrAlsNix/zf-couchbase2
 */

namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use Zend\Cache\Exception;
use Zend\Cache\Storage\Adapter\AbstractAdapter;
use Zend\Cache\Storage\Capabilities;
use Zend\Cache\Storage\FlushableInterface;

/**
 * Class Couchbase
 * @package MehrAlsNix\ZF\Cache\Storage\Adapter
 */
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
            $v = (string)phpversion('couchbase');
            static::$extCouchbaseMajorVersion = ($v !== '') ? (int)$v[0] : 0;
        }
        if (static::$extCouchbaseMajorVersion < 1) {
            throw new Exception\ExtensionNotLoadedException('Need ext/couchbase version >= 2.0.0');
        }
        parent::__construct($options);
        // reset initialized flag on update option(s)
        $initialized = &$this->initialized;
        $this->getEventManager()->attach('option', function () use (& $initialized) {
            $initialized = false;
        });
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
     * Internal method to store an item.
     *
     * @param  string $normalizedKey
     * @param  mixed $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $memc = $this->getCouchbaseResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;

        try {
            $memc->upsert($internalKey, $value, ['expiry' => $this->expirationTime()]);
        } catch (\CouchbaseException $e) {
            if ($e->getCode() === CouchbaseErrors::LCB_KEY_ENOENT) {
                return false;
            }

            throw new Exception\RuntimeException($e->getMessage());
        }

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
            $this->resourceId = $options->getResourceId();

            // init namespace prefix
            $namespace = $options->getNamespace();
            $this->namespacePrefix = '';

            if ($namespace !== '') {
                $this->namespacePrefix = $namespace . $options->getNamespaceSeparator();
            }

            // update initialized flag
            $this->initialized = true;
        }

        return $this->resourceManager->getResource($this->resourceId);
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
     * Get expiration time by ttl
     *
     * Some storage commands involve sending an expiration value (relative to
     * an item or to an operation requested by the client) to the server. In
     * all such cases, the actual value sent may either be Unix time (number of
     * seconds since January 1, 1970, as an integer), or a number of seconds
     * starting from current time. In the latter case, this number of seconds
     * may not exceed 60*60*24*30 (number of seconds in 30 days); if the
     * expiration value is larger than that, the server will consider it to be
     * real Unix time value rather than an offset from current time.
     *
     * @return int
     */
    protected function expirationTime()
    {
        $ttl = $this->getOptions()->getTtl();
        if ($ttl > 2592000) {
            return time() + $ttl;
        }
        return $ttl;
    }

    /**
     * Add an item.
     *
     * @param  string $normalizedKey
     * @param  mixed $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        $memc = $this->getCouchbaseResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        try {
            $memc->insert($internalKey, $value, ['expiry' => $this->expirationTime()]);
        } catch (\CouchbaseException $e) {
            if ($e->getCode() === CouchbaseErrors::LCB_KEY_EEXISTS) {
                return false;
            }
            throw new Exception\RuntimeException($e);
        }

        return true;
    }

    /**
     * @param array $normalizedKeyValuePairs
     * @return array|mixed
     * @throws Exception\RuntimeException
     */
    protected function internalAddItems(array & $normalizedKeyValuePairs)
    {
        $memc = $this->getCouchbaseResource();

        $namespacedKeyValuePairs = $this->namespacesKeyValuePairs($normalizedKeyValuePairs);

        try {
            $result = $memc->insert($namespacedKeyValuePairs, null, ['expiry' => $this->expirationTime()]);
        } catch (\CouchbaseException $e) {
            throw new Exception\RuntimeException($e);
        }

        $this->filterErrors($result);

        return $result;
    }

    /**
     * @param array $normalizedKeyValuePairs
     * @return array
     */
    protected function namespacesKeyValuePairs(array & $normalizedKeyValuePairs)
    {
        $namespacedKeyValuePairs = [];

        foreach ($normalizedKeyValuePairs as $normalizedKey => & $value) {
            $namespacedKeyValuePairs[$this->namespacePrefix . $normalizedKey] = ['value' => & $value];
        }
        unset($value);

        return $namespacedKeyValuePairs;
    }

    /**
     * @param $result
     */
    protected function filterErrors(& $result)
    {
        foreach ($result as $key => $document) {
            if (!$document->error instanceof \CouchbaseException) {
                unset($result[$key]);
            }
        }

        $result = array_keys($result);

        $this->removeNamespacePrefix($result);
    }

    /**
     * @param array $result
     */
    protected function removeNamespacePrefix(& $result)
    {
        if ($result && $this->namespacePrefix !== '') {
            $nsPrefixLength = strlen($this->namespacePrefix);
            foreach ($result as & $internalKey) {
                $internalKey = substr($internalKey, $nsPrefixLength);
            }
        }
    }

    /**
     * Internal method to store multiple items.
     *
     * @param  array $normalizedKeyValuePairs
     * @return array Array of not stored keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalSetItems(array & $normalizedKeyValuePairs)
    {
        $memc = $this->getCouchbaseResource();

        $namespacedKeyValuePairs = $this->namespacesKeyValuePairs($normalizedKeyValuePairs);

        $memc->upsert($namespacedKeyValuePairs, null, ['expiry' => $this->expirationTime()]);

        return [];
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
        $memc = $this->getCouchbaseResource();
        $result = true;

        try {
            $memc->remove($this->namespacePrefix . $normalizedKey);
        } catch (\CouchbaseException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Internal method to remove multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Array of not removed keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalRemoveItems(array & $normalizedKeys)
    {
        $memc = $this->getCouchbaseResource();

        $this->namespacesKeys($normalizedKeys);

        try {
            $result = $memc->remove($normalizedKeys);
        } catch (\CouchbaseException $e) {
            throw new Exception\RuntimeException($e);
        }

        $this->filterErrors($result);

        return $result;
    }

    protected function namespacesKeys(array & $normalizedKeys)
    {
        foreach ($normalizedKeys as & $normalizedKey) {
            $normalizedKey = $this->namespacePrefix . $normalizedKey;
        }
    }

    /**
     * Internal method to set an item only if token matches
     *
     * @param  mixed $token
     * @param  string $normalizedKey
     * @param  mixed $value
     * @return bool
     * @throws Exception\ExceptionInterface
     * @see    getItem()
     * @see    setItem()
     */
    protected function internalCheckAndSetItem(& $token, & $normalizedKey, & $value)
    {
        $memc = $this->getCouchbaseResource();
        $key = $this->namespacePrefix . $normalizedKey;
        $success = null;
        $this->internalGetItem($key, $success, $token);

        try {
            $memc->replace($key, $value, ['cas' => $token, 'expiry' => $this->expirationTime()]);
            $result = true;
        } catch (\CouchbaseException $e) {
            if ($e->getCode() === CouchbaseErrors::LCB_KEY_EEXISTS
                || $e->getCode() === CouchbaseErrors::LCB_KEY_ENOENT
            ) {
                return false;
            }
            throw new Exception\RuntimeException($e);
        }

        return $result;
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
        $memc = $this->getCouchbaseResource();

        try {
            $document = $memc->get($internalKey);
            $result = $document->value;
            $casToken = $document->cas;
            $success = true;
        } catch (\CouchbaseException $e) {
            if ($e->getCode() === CouchbaseErrors::LCB_KEY_ENOENT) {
                $result = null;
                $success = false;
            } else {
                throw new Exception\RuntimeException($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Internal method to set an item only if token matches
     *
     * @param  string $normalizedKey
     * @param  mixed $value
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $result = true;

        $memc = $this->getCouchbaseResource();
        $expiration = $this->expirationTime();
        try {
            $memc->replace($this->namespacePrefix . $normalizedKey, $value, ['expiry' => $expiration]);
        } catch (\CouchbaseException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Internal method to touch an item.
     *
     * @param string &$normalizedKey Key which will be touched
     *
     * @return bool
     * @throws Exception\RuntimeException
     */
    protected function internalTouchItem(& $normalizedKey)
    {
        $redis = $this->getCouchbaseResource();
        try {
            $ttl = $this->getOptions()->getTtl();
            $redis->touch($this->namespacePrefix . $normalizedKey, $ttl);
        } catch (\CouchbaseException $e) {
            if ($e->getCode() === CouchbaseErrors::LCB_KEY_EEXISTS
                || $e->getCode() === CouchbaseErrors::LCB_KEY_ENOENT
            ) {
                return false;
            }
            throw new Exception\RuntimeException($e);
        }

        return true;
    }

    /**
     * Internal method to test if an item exists.
     *
     * @param  string $normalizedKey
     * @return bool
     * @throws Exception\ExceptionInterface
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $memc = $this->getCouchbaseResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;

        try {
            $result = $memc->get($internalKey)->value;
        } catch (\CouchbaseException $e) {
            if ($e->getCode() === CouchbaseErrors::LCB_KEY_ENOENT) {
                $result = false;
            } else {
                throw new Exception\RuntimeException($e);
            }
        }

        return (bool)$result;
    }

    /**
     * Internal method to test multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Array of found keys
     * @throws Exception\ExceptionInterface
     */
    protected function internalHasItems(array & $normalizedKeys)
    {
        $memc = $this->getCouchbaseResource();

        $this->namespacesKeys($normalizedKeys);

        try {
            $result = $memc->get($normalizedKeys);
            foreach ($result as $key => $element) {
                if ($element->error instanceof \CouchbaseException
                    && $element->error->getCode() === CouchbaseErrors::LCB_KEY_ENOENT
                ) {
                    unset($result[$key]);
                }
            }
        } catch (\CouchbaseException $e) {
            return [];
        }

        $result = array_keys($result);

        $this->removeNamespacePrefix($result);

        return $result;
    }

    /**
     * Internal method to get multiple items.
     *
     * @param  array $normalizedKeys
     * @return array Associative array of keys and values
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetItems(array & $normalizedKeys)
    {
        $memc = $this->getCouchbaseResource();

        $this->namespacesKeys($normalizedKeys);

        try {
            $result = $memc->get($normalizedKeys);
            foreach ($result as $key => $element) {
                if ($element->error instanceof \CouchbaseException
                    && $element->error->getCode() === CouchbaseErrors::LCB_KEY_ENOENT
                ) {
                    unset($result[$key]);
                } else {
                    $result[$key] = $element->value;
                }
            }
        } catch (\CouchbaseException $e) {
            return [];
        }

        // remove namespace prefix from result
        if ($result && $this->namespacePrefix !== '') {
            $tmp = [];
            $nsPrefixLength = strlen($this->namespacePrefix);
            foreach ($result as $internalKey => $value) {
                $tmp[substr($internalKey, $nsPrefixLength)] = $value instanceof \CouchbaseMetaDoc ? $value->value : $value;
            }
            $result = $tmp;
        }

        return $result;
    }

    /**
     * Internal method to increment an item.
     *
     * @param  string $normalizedKey
     * @param  int $value
     * @return int|bool The new value on success, false on failure
     * @throws Exception\ExceptionInterface
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $couchbaseBucket = $this->getCouchbaseResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $value = (int)$value;
        $newValue = false;

        try {
            $newValue = $couchbaseBucket->counter($internalKey, $value, ['initial' => $value, 'expiry' => $this->expirationTime()])->value;
        } catch (\CouchbaseException $e) {
            try {
                if ($e->getCode() === CouchbaseErrors::LCB_KEY_ENOENT) {
                    $newValue = $couchbaseBucket->insert($internalKey, $value, ['expiry' => $this->expirationTime()])->value;
                }
            } catch (\CouchbaseException $e) {
                throw new Exception\RuntimeException($e);
            }
        }

        return $newValue;
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
            $this->capabilities = new Capabilities(
                $this,
                $this->capabilityMarker,
                [
                    'supportedDatatypes' => [
                        'NULL' => true,
                        'boolean' => true,
                        'integer' => true,
                        'double' => true,
                        'string' => true,
                        'array' => false,
                        'object' => false,
                        'resource' => false,
                    ],
                    'supportedMetadata' => [],
                    'minTtl' => 1,
                    'maxTtl' => 0,
                    'staticTtl' => true,
                    'ttlPrecision' => 1,
                    'useRequestTime' => false,
                    'expiredRead' => false,
                    'maxKeyLength' => 255,
                    'namespaceIsPrefix' => true,
                ]
            );
        }

        return $this->capabilities;
    }
}
