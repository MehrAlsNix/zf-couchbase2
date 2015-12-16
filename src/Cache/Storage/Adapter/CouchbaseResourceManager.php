<?php

namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use CouchbaseCluster as CouchbaseClusterResource;
use Zend\Cache\Exception;
use Zend\Stdlib\ArrayUtils;

class CouchbaseResourceManager
{
    /**
     * Registered resources
     *
     * @var array
     */
    protected $resources = [];

    /**
     * Get servers
     * @param string $id
     * @throws Exception\RuntimeException
     * @return array array('host' => <host>, 'port' => <port>)
     */
    public function getServer($id)
    {
        if (!$this->hasResource($id)) {
            throw new Exception\RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof CouchbaseClusterResource) {
            return $resource->manager()->info()['hostname'];
        }
        return $resource['server'];
    }

    /**
     * Normalize one server into the following format:
     * array('host' => <host>, 'port' => <port>, 'weight' => <weight>)
     *
     * @param string|array &$server
     * @throws Exception\InvalidArgumentException
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
                $host = (string)$server[0];
                $port = isset($server[1]) ? (int)$server[1] : $port;
            }
            // array('host' => <host>[, 'port' => <port>])
            if (!isset($server[0]) && isset($server['host'])) {
                $host = (string)$server['host'];
                $port = isset($server['port']) ? (int)$server['port'] : $port;
            }
        } else {
            // parse server from URI host{:?port}
            $server = trim($server);
            if (strpos($server, '://') === false) {
                $server = 'http://' . $server;
            }
            $server = parse_url($server);
            if (!$server) {
                throw new Exception\InvalidArgumentException("Invalid server given");
            }
            $host = $server['host'];
            $port = isset($server['port']) ? (int)$server['port'] : $port;
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
            'port' => $port
        ];
    }

    /**
     * Check if a resource exists
     *
     * @param string $id
     * @return bool
     */
    public function hasResource($id)
    {
        return isset($this->resources[$id]);
    }

    /**
     * Gets a couchbase cluster resource
     *
     * @param string $id
     * @return \CouchbaseBucket
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
        $memc = new CouchbaseClusterResource('http://' . $resource['server'][0]['host'] . ':' . $resource['server'][0]['port'], (string) $resource['username'], (string) $resource['password']);
        $bucket = $memc->openBucket((string) $resource['bucket'], (string) $resource['password']);

        // buffer and return
        $this->resources[$id] = $bucket;
        return $bucket;
    }

    /**
     * Set a resource
     *
     * @param string $id
     * @param array|\Traversable|CouchbaseClusterResource $resource
     * @return CouchbaseResourceManager Fluent interface
     */
    public function setResource($id, $resource)
    {
        $id = (string)$id;
        if (!($resource instanceof CouchbaseClusterResource)) {
            if ($resource instanceof \Traversable) {
                $resource = ArrayUtils::iteratorToArray($resource);
            } elseif (!is_array($resource)) {
                throw new Exception\InvalidArgumentException(
                    'Resource must be an instance of CouchbaseCluster or an array or Traversable'
                );
            }
            $resource = array_merge([
                'server' => '',
                'username' => '',
                'password' => '',
                'bucket' => ''
            ], $resource);
            // normalize and validate params
            $this->normalizeServers($resource['server']);
        }
        $this->resources[$id] = $resource;
        return $this;
    }

    /**
     * Remove a resource
     *
     * @param string $id
     * @return CouchbaseResourceManager Fluent interface
     */
    public function removeResource($id)
    {
        unset($this->resources[$id]);
        return $this;
    }

    /**
     * Set servers
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $server
     * @return CouchbaseResourceManager
     */
    public function setServer($id, $server)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'server' => $server
            ]);
        }
        $this->normalizeServers($server);
        $resource = &$this->resources[$id];
        $resource['server'] = $server;

        return $this;
    }

    /**
     * Add servers
     *
     * @param string $id
     * @param string|array $servers
     * @return CouchbaseResourceManager
     */
    public function addServers($id, $servers)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'servers' => $servers
            ]);
        }
        $this->normalizeServers($servers);
        $resource = &$this->resources[$id];
        if ($resource instanceof CouchbaseClusterResource) {
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
     * Add one server
     *
     * @param string $id
     * @param string|array $server
     * @return CouchbaseResourceManager
     */
    public function addServer($id, $server)
    {
        return $this->addServers($id, [$server]);
    }

    /**
     * Set servers
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $server
     * @return CouchbaseResourceManager
     */
    public function setUsername($id, $username)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'username' => $username
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
     * Set servers
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $server
     * @return CouchbaseResourceManager
     */
    public function setBucket($id, $bucket)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'bucket' => $bucket
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
     * Set servers
     *
     * $servers can be an array list or a comma separated list of servers.
     * One server in the list can be descripted as follows:
     * - URI:   [tcp://]<host>[:<port>][?weight=<weight>]
     * - Assoc: array('host' => <host>[, 'port' => <port>][, 'weight' => <weight>])
     * - List:  array(<host>[, <port>][, <weight>])
     *
     * @param string $id
     * @param string $server
     * @return CouchbaseResourceManager
     */
    public function setPassword($id, $password)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [
                'password' => $password
            ]);
        }

        $resource = &$this->resources[$id];
        $resource['password'] = $password;

        return $this;
    }

    /**
     * Normalize a list of servers into the following format:
     * array(array('host' => <host>, 'port' => <port>, 'weight' => <weight>)[, ...])
     *
     * @param string|array $servers
     */
    protected function normalizeServers(& $servers)
    {
        if (!is_array($servers) && !$servers instanceof \Traversable) {
            // Convert string into a list of servers
            $servers = explode(',', $servers);
        }
        $result = [];
        foreach ($servers as $server) {
            $this->normalizeServer($server);
            $result[$server['host'] . ':' . $server['port']] = $server;
        }
        $servers = array_values($result);
    }

    /**
     * Compare 2 normalized server arrays
     * (Compares only the host and the port)
     *
     * @param array $serverA
     * @param array $serverB
     * @return int
     */
    protected function compareServers(array $serverA, array $serverB)
    {
        $keyA = $serverA['host'] . ':' . $serverA['port'];
        $keyB = $serverB['host'] . ':' . $serverB['port'];
        if ($keyA === $keyB) {
            return 0;
        }
        return $keyA > $keyB ? 1 : -1;
    }
}
