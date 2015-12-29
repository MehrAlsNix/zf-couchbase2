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

namespace MehrAlsNix\Test\ZF\Cache\Storage\Adapter;

use MehrAlsNix\ZF\Cache\Storage\Adapter\Couchbase;
use MehrAlsNix\ZF\Cache\Storage\Adapter\CouchbaseOptions;
use Zend\Cache\Exception\ExtensionNotLoadedException;

/**
 * Class CouchbaseTest
 * @package MehrAlsNix\Test\ZF\Cache\Storage\Adapter
 */
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
                $this->markTestSkipped("Missing ext/couchbase version >= 2.0.0");
            }
        }
        $this->_options = new CouchbaseOptions([
            'resource_id' => __CLASS__
        ]);
        if (getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST') && getenv('TESTS_ZEND_CACHE_COUCHBASE_PORT')) {
            $this->_options->getResourceManager()->setServer(__CLASS__, [
                [getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST'), getenv('TESTS_ZEND_CACHE_COUCHBASE_PORT')]
            ]);
        } elseif (getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST')) {
            $this->_options->getResourceManager()->setServer(__CLASS__, [
                [getenv('TESTS_ZEND_CACHE_COUCHBASE_HOST')]
            ]);
        }
        if (getenv('TESTS_ZEND_CACHE_COUCHBASE_USERNAME')) {
            $this->_options->getResourceManager()->setUsername(__CLASS__, getenv('TESTS_ZEND_CACHE_COUCHBASE_USERNAME'));
        }
        if (getenv('TESTS_ZEND_CACHE_COUCHBASE_PASSWORD')) {
            $this->_options->getResourceManager()->setPassword(__CLASS__, getenv('TESTS_ZEND_CACHE_COUCHBASE_PASSWORD'));
        }
        if (getenv('TESTS_ZEND_CACHE_COUCHBASE_BUCKET')) {
            $this->_options->getResourceManager()->setBucket(__CLASS__, getenv('TESTS_ZEND_CACHE_COUCHBASE_BUCKET'));
        }
        $this->_storage = new Couchbase();
        $this->_storage->setOptions($this->_options);
        $this->_storage->flush();
        parent::setUp();
    }

    public function testLibOptionsSet()
    {
        $options = new CouchbaseOptions();
        $options->setLibOptions([
            'SERTYPE_IGBINARY' => false
        ]);
        $this->assertEquals($options->getResourceManager()->getLibOptions(
            $options->getResourceId()
        ), false);
        $couchbase = new Couchbase($options);
        $this->assertEquals($couchbase->getOptions()->getLibOptions(), [
            \COUCHBASE_SERTYPE_IGBINARY => false
        ]);
    }


    public function tearDown()
    {
        if ($this->_storage) {
            $this->_storage->flush();
        }
        parent::tearDown();
    }
}
