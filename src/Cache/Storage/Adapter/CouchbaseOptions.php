<?php

namespace MehrAlsNix\ZF\Cache\Storage\Adapter;

use Zend\Cache\Storage\Adapter\AdapterOptions;
use Zend\Cache\Exception;

class CouchbaseOptions extends AdapterOptions
{
    /**
     * The namespace separator
     * @var string
     */
    protected $namespaceSeparator = ':';

    /**
     * The couchbase resource manager
     *
     * @var null|CouchbaseResourceManager
     */
    protected $resourceManager;

    /**
     * The resource id of the resource manager
     *
     * @var string
     */
    protected $resourceId = 'default';

    /**
     * Set namespace.
     *
     *
     * @see AdapterOptions::setNamespace()
     * @see CouchbaseOptions::setPrefixKey()
     *
     * @param string $namespace
     *
     * @return AdapterOptions
     *
     * @throws Exception\InvalidArgumentException
     */
    public function setNamespace($namespace)
    {
        $namespace = (string) $namespace;

        if (128 < strlen($namespace)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a prefix key of no longer than 128 characters',
                __METHOD__
            ));
        }

        return parent::setNamespace($namespace);
    }

    /**
     * Set namespace separator
     *
     * @param  string $namespaceSeparator
     * @return CouchbaseOptions
     */
    public function setNamespaceSeparator($namespaceSeparator)
    {
        $namespaceSeparator = (string) $namespaceSeparator;
        if ($this->namespaceSeparator !== $namespaceSeparator) {
            $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
            $this->namespaceSeparator = $namespaceSeparator;
        }
        return $this;
    }

    /**
     * Get namespace separator
     *
     * @return string
     */
    public function getNamespaceSeparator()
    {
        return $this->namespaceSeparator;
    }

    /**
     * Set the couchbase resource manager to use
     *
     * @param null|CouchbaseResourceManager $resourceManager
     * @return CouchbaseOptions
     */
    public function setResourceManager(CouchbaseResourceManager $resourceManager = null)
    {
        if ($this->resourceManager !== $resourceManager) {
            $this->triggerOptionEvent('resource_manager', $resourceManager);
            $this->resourceManager = $resourceManager;
        }
        return $this;
    }

    /**
     * Get the couchbase resource manager
     *
     * @return CouchbaseResourceManager
     */
    public function getResourceManager()
    {
        if (!$this->resourceManager) {
            $this->resourceManager = new CouchbaseResourceManager();
        }
        return $this->resourceManager;
    }

    /**
     * Get the couchbase resource id
     *
     * @return string
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * Set the couchbase resource id
     *
     * @param string $resourceId
     * @return CouchbaseOptions
     */
    public function setResourceId($resourceId)
    {
        $resourceId = (string) $resourceId;
        if ($this->resourceId !== $resourceId) {
            $this->triggerOptionEvent('resource_id', $resourceId);
            $this->resourceId = $resourceId;
        }
        return $this;
    }

    /**
     * Get the persistent id
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->getResourceManager()->getPassword($this->getResourceId());
    }

    /**
     * Set the persistent id
     *
     * @param string $persistentId
     * @return CouchbaseOptions
     */
    public function setPassword($password)
    {
        $this->getResourceManager()->setPassword($this->getResourceId(), $password);
        return $this;
    }

    /**
     * Get the persistent id
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->getResourceManager()->getUsername($this->getResourceId());
    }

    /**
     * Set the persistent id
     *
     * @param string $persistentId
     * @return CouchbaseOptions
     */
    public function setUsername($username)
    {
        $this->getResourceManager()->setUsername($this->getResourceId(), $username);
        return $this;
    }

    /**
     * Set a list of couchbase servers to add on initialize
     *
     * @param string $server server
     * @return CouchbaseOptions
     * @throws Exception\InvalidArgumentException
     */
    public function setServer($server)
    {
        $this->getResourceManager()->setServer($this->getResourceId(), $server);
        return $this;
    }

    /**
     * Get Servers
     *
     * @return array
     */
    public function getServer()
    {
        return $this->getResourceManager()->getServer($this->getResourceId());
    }
}
