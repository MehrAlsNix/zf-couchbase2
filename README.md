# zf-couchbase2

## The Couchbase Adapter

The `MehrAlsNix\ZF\Cache\Storage\Adapter\Couchbase` adapter stores cache items
over the couchbase protocol. It’s using the required PHP extension couchbase
which is based on Libcouchbase.

This adapter implements the following interfaces:

- `Zend\Cache\Storage\StorageInterface`
- `Zend\Cache\Storage\FlushableInterface`
