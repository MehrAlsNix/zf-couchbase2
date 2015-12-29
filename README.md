# zf-couchbase2

[![Build Status](https://scrutinizer-ci.com/g/MehrAlsNix/zf-couchbase2/badges/build.png?b=develop)](https://scrutinizer-ci.com/g/MehrAlsNix/zf-couchbase2/build-status/develop) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/MehrAlsNix/zf-couchbase2/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/MehrAlsNix/zf-couchbase2/?branch=develop) [![Code Coverage](https://scrutinizer-ci.com/g/MehrAlsNix/zf-couchbase2/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/MehrAlsNix/zf-couchbase2/?branch=develop)

## The Couchbase Adapter

The `MehrAlsNix\ZF\Cache\Storage\Adapter\Couchbase` adapter stores cache items
over the couchbase protocol. It’s using the required PHP extension [couchbase](https://github.com/couchbase/php-couchbase)
which is based on [Libcouchbase](https://github.com/couchbase/libcouchbase).

This adapter implements the following interfaces:

- `Zend\Cache\Storage\StorageInterface`
- `Zend\Cache\Storage\FlushableInterface`
