<?php
/**
 * zf-couchbase2.
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
 *
 * @link      http://github.com/MehrAlsNix/zf-couchbase2
 */
namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use CouchbaseBucket as CouchbaseBucketResource;
use CouchbaseCluster as CouchbaseClusterResource;
use Zend\Cache\Exception;
use Zend\Stdlib\ArrayUtils;

/**
 * Class CouchbaseResourceManager.
 */
class CouchbaseResourceManager
{
    /**
     * Registered resources.
     *
     * @var array
     */
    protected $resources = [];

    /**
     * Get servers.
     *
     * @param string $id
     *
     * @throws Exception\RuntimeException
     *
     * @return array array('host' => <host>, 'port' => <port>)
     */
    public function getServer($id)
    {
        if (!$this->hasResource($id)) {
            throw new Exception\RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof CouchbaseBucketResource) {
            return $resource->manager()->info()['hostname'];
        }

        return $resource['server'];
    }

    /**
     * Normalize one server into the following format:
     * array('host' => <host>, 'port' => <port>, 'weight' => <weight>).
     *
     * @param string|array &$server
     *
     * @throws Exception\InvalidArgumentException
     * @throws \Zend\Stdlib\Exception\InvalidArgumentException
     */
    protected function normalizeServer(&$server)
    {
        $host = null;
        $port = 8091;
        // convert a single server into an array
        if ($server instanceof \Traversable) {
            $server = ArrayUtils::iteratorToArray($server);
        }
        if (is_array($server)) {
            // array(<host>[, <port>])
            if (isset($server[0])) {
                $host = (string) $server[0];
                $port = isset($server[1]) ? (int) $server[1] : $port;
            }
            // array('host' => <host>[, 'port' => <port>])
            if (!isset($server[0]) && isset($server['host'])) {
                $host = (string) $server['host'];
                $port = isset($server['port']) ? (int) $server['port'] : $port;
            }
        } else {
            // parse server from URI host{:?port}
            $server = trim($server);
            if (strpos($server, '://') === false) {
                $server = 'http://'.$server;
            }
            $server = parse_url($server);
            if (!$server) {
                throw new Exception\InvalidArgumentException('Invalid server given');
            }
            $host = $server['host'];
            $port = isset($server['port']) ? (int) $server['port'] : $port;
            if (isset($server['query'])) {
                $query = null;
                parse_str($server['query'], $query);
            }
        }
        if (!$host) {
            throw new Exception\InvalidArgumentException('Missing required server host');
        }
        $server = [
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * Check if a resource exists.
     *
     * @param string $id
     *
     * @return bool
     */
    public function hasResource($id)
    {
        return isset($this->resources[$id]);
    }

    /**
     * Gets a couchbase cluster resource.
     *
     * @param string $id
     *
     * @return \CouchbaseBucket
     *
     * @throws Exception\RuntimeException
     */
    public function getResource($id)
    {
        if (!$this->hasResource($id)) {
            throw new Exception\RuntimeException("No resource with id '{$id}'");
        }
        $resource = $this->resources[$id];
        if ($resource instanceof \CouchbaseBucket) {
            return $resource;
        }
        $memc = new CouchbaseClusterResource('couchbase://'.$resource['server'][0]['host'].':'.$resource['server'][0]['port'], (string) $resource['username'], (string) $resource['password']);
        $bucket = $memc->openBucket((string) $resource['bucket'], (string) $resource['password']);
        /*
        $bucket->setTranscoder(
            '\MehrAlsNix\ZF\Cache\Storage\Adapter\CouchbaseResourceManager::encoder',
            '\MehrAlsNix\ZF\Cache\Storage\Adapter\CouchbaseResourceManager::decoder'
        );
        */

        // buffer and return
        $this->resources[$id] = $bucket;

        return $bucket;
    }

    /**
     * Set a resource.
     *
     * @param string                                     $id
     * @param array|\Traversable|CouchbaseBucketResource $resource
     *
     * @return CouchbaseResourceManager Fluent interface
     *
     * @throws \Zend\Stdlib\Exception\InvalidArgumentException
     * @throws Exception\InvalidArgumentException
     */
    public function setResource($id, $resource)
    {
        $id = (string) $id;
        if (!($resource instanceof CouchbaseBucketResource)) {
            if ($resource instanceof \Traversable) {
                $resource = ArrayUtils::iteratorToArray($resource);
            } elseif (!is_array($resource)) {
                throw new Exception\InvalidArgumentException(
                    'Resource must be an instance of CouchbaseCluster or an array or Traversable'
                );
            }
            $resource = array_merge([
                'lib_options' => [],
                'server' => '',
                'username' => '',
                'password' => '',
                'bucket' => '',
            ], $resource);
            // normalize and validate params
            $this->normalizeServers($resource['server']);
            $this->normalizeLibOptions($resource['lib_options']);
        }
        $this->resources[$id] = $resource;

        return $this;
    }

    /**
     * Normalize libmemcached options.
     *
     * @param array|\Traversable $libOptions
     *
     * @throws Exception\InvalidArgumentException
     */
    protected function normalizeLibOptions(&$libOptions)
    {
        if (!is_array($libOptions) && !($libOptions instanceof \Traversable)) {
            throw new Exception\InvalidArgumentException(
                'Lib-Options must be an array or an instance of Traversable'
            );
        }

        $result = [];
        foreach ($libOptions as $key => $value) {
            $this->normalizeLibOptionKey($key);
            $result[$key] = $value;
        }

        $libOptions = $result;
    }

    /**
     * Convert option name into it's constant value.
     *
     * @param string|int $key
     *
     * @throws Exception\InvalidArgumentException
     */
    protected function normalizeLibOptionKey(&$key)
    {
        // convert option name into it's constant value
        if (is_string($key)) {
            $const = '\\COUCHBASE_SERTYPE_'.str_replace([' ', '-'], '_', strtoupper($key));
            if (!defined($const)) {
                throw new Exception\InvalidArgumentException("Unknown libcouchbase option '{$key}' ({$const})");
            }
            $key = constant($const);
        } else {
            $key = (int) $key;
        }
    }

    /**
     * Set Libmemcached options.
     *
     * @param string $id
     * @param array  $libOptions
     *
     * @return CouchbaseResourceManager Fluent interface
     */
    public function setLibOptions($id, array $libOptions)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'lib_options' => $libOptions,
            ]);
        }

        $this->normalizeLibOptions($libOptions);

        $resource = &$this->resources[$id];
        $resource['lib_options'] = $libOptions;

        return $this;
    }

    /**
     * Get Libmemcached options.
     *
     * @param string $id
     *
     * @return array
     *
     * @throws Exception\RuntimeException
     */
    public function getLibOptions($id)
    {
        if (!$this->hasResource($id)) {
            throw new Exception\RuntimeException("No resource with id '{$id}'");
        }

        $resource = &$this->resources[$id];

        if ($resource instanceof MemcachedResource) {
            $libOptions = [];
            $reflection = new ReflectionClass('Memcached');
            $constants = $reflection->getConstants();
            foreach ($constants as $constName => $constValue) {
                if (substr($constName, 0, 4) == 'OPT_') {
                    $libOptions[$constValue] = $resource->getOption($constValue);
                }
            }

            return $libOptions;
        }

        return $resource['lib_options'];
    }

    /**
     * Remove a resource.
     *
     * @param string $id
     *
     * @return CouchbaseResourceManager Fluent interface
     */
    public function removeResource($id)
    {
        unset($this->resources[$id]);

        return $this;
    }

    /**
     * Set servers.
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $server
     *
     * @return CouchbaseResourceManager
     */
    public function setServer($id, $server)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'server' => $server,
            ]);
        }
        $this->normalizeServers($server);
        $resource = &$this->resources[$id];
        $resource['server'] = $server;

        return $this;
    }

    /**
     * Add servers.
     *
     * @param string       $id
     * @param string|array $servers
     *
     * @return CouchbaseResourceManager
     */
    public function addServers($id, $servers)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'servers' => $servers,
            ]);
        }
        $this->normalizeServers($servers);
        $resource = &$this->resources[$id];
        if ($resource instanceof CouchbaseBucketResource) {
            // don't add servers twice
            $servers = array_udiff($servers, $resource->getServerList(), [$this, 'compareServers']);
            if ($servers) {
                $resource->addServers($servers);
            }
        } else {
            // don't add servers twice
            $resource['servers'] = array_merge(
                $resource['servers'],
                array_udiff($servers, $resource['servers'], [$this, 'compareServers'])
            );
        }

        return $this;
    }

    /**
     * Add one server.
     *
     * @param string       $id
     * @param string|array $server
     *
     * @return CouchbaseResourceManager
     */
    public function addServer($id, $server)
    {
        return $this->addServers($id, [$server]);
    }

    /**
     * Set servers.
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $username
     *
     * @return CouchbaseResourceManager
     */
    public function setUsername($id, $username)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'username' => $username,
            ]);
        }

        $resource = &$this->resources[$id];
        $resource['username'] = $username;

        return $this;
    }

    public function getUsername($id)
    {
        if (!$this->hasResource($id)) {
            return $this->getResource($id);
        }

        $resource = &$this->resources[$id];

        return $resource['username'];
    }

    /**
     * Set servers.
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $bucket
     *
     * @return CouchbaseResourceManager
     */
    public function setBucket($id, $bucket)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'bucket' => $bucket,
            ]);
        }

        $resource = &$this->resources[$id];
        $resource['bucket'] = $bucket;

        return $this;
    }

    public function getBucket($id)
    {
        if (!$this->hasResource($id)) {
            return $this->getResource($id);
        }

        $resource = &$this->resources[$id];

        return $resource['bucket'];
    }

    /**
     * Set servers.
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $password
     *
     * @return CouchbaseResourceManager
     */
    public function setPassword($id, $password)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'password' => $password,
            ]);
        }

        $resource = &$this->resources[$id];
        $resource['password'] = $password;

        return $this;
    }

    public function getPassword($id)
    {
        if (!$this->hasResource($id)) {
            return $this->getResource($id);
        }

        $resource = &$this->resources[$id];

        return $resource['password'];
    }

    /**
     * Normalize a list of servers into the following format:
     * array(array('host' => <host>, 'port' => <port>, 'weight' => <weight>)[, ...]).
     *
     * @param string|array $servers
     *
     * @throws \Zend\Stdlib\Exception\InvalidArgumentException
     */
    protected function normalizeServers(&$servers)
    {
        if (!is_array($servers) && !$servers instanceof \Traversable) {
            // Convert string into a list of servers
            $servers = explode(',', $servers);
        }
        $result = [];
        foreach ($servers as $server) {
            $this->normalizeServer($server);
            $result[$server['host'].':'.$server['port']] = $server;
        }
        $servers = array_values($result);
    }

    /**
     * Compare 2 normalized server arrays
     * (Compares only the host and the port).
     *
     * @param array $serverA
     * @param array $serverB
     *
     * @return int
     */
    protected function compareServers(array $serverA, array $serverB)
    {
        $keyA = $serverA['host'].':'.$serverA['port'];
        $keyB = $serverB['host'].':'.$serverB['port'];
        if ($keyA === $keyB) {
            return 0;
        }

        return $keyA > $keyB ? 1 : -1;
    }
}
