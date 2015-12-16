<?php

namespace MehrAlsNix\Test\ZF\Cache\Storage\Adapter;

use MehrAlsNix\ZF\Cache\Storage\Adapter\Couchbase;
use MehrAlsNix\ZF\Cache\Storage\Adapter\CouchbaseOptions;
use Zend\Cache\Exception\ExtensionNotLoadedException;

class CouchbaseTest extends CommonAdapterTest
{
    public function setUp()
    {
        if (!getenv('TESTS_ZEND_CACHE_COUCHBASE_ENABLED')) {
            $this->markTestSkipped('Enable TESTS_ZEND_CACHE_COUCHBASE_ENABLED to run this test');
        }
        if (version_compare('2.0.0', phpversion('couchbase')) > 0) {
            try {
                new Couchbase();
                $this->fail("Expected exception Zend\Cache\Exception\ExtensionNotLoadedException");
            } catch (ExtensionNotLoadedException $e) {
                $this->markTestSkipped("Missing ext/memcache version >= 2.0.0");
            }
        }
        $this->_options  = new CouchbaseOptions([
            'resource_id' => __CLASS__
        ]);
        if (getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST') && getenv('TESTS_ZEND_CACHE_COUCHBASE_PORT')) {
            $this->_options->getResourceManager()->addServers(__CLASS__, [
                [getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST'), getenv('TESTS_ZEND_CACHE_COUCHBASE_PORT')]
            ]);
        } elseif (getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST')) {
            $this->_options->getResourceManager()->addServers(__CLASS__, [
                [getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST')]
            ]);
        }
        $this->_storage = new Couchbase();
        $this->_storage->setOptions($this->_options);
        $this->_storage->flush();
        parent::setUp();
    }
}
