<?php

use PHPUnit\Framework\TestCase;

use Fleks\Util\Config;
use Fleks\Util\Container;
use Fleks\Db\Generic;
use Fleks\Db\MySQL;
use Fleks\Storage\StorageObject;
use Fleks\Storage\Db\Resultset;
use Fleks\Storage\Db\AbstractStorage;

class Storage_Db_Abstract_TestObject extends StorageObject{}
class Storage_Db_Abstract_TestcaseObject extends StorageObject{}
class Storage_Db_Abstract_ItemObject extends StorageObject{}

class Storage_Db_Abstract_TestStorage extends AbstractStorage
{
    protected $tableName = 'tests';
    protected $objectClass = Storage_Db_Abstract_TestObject::class;

    protected $relations = [
        'testcases' => [ 'type' => self::ONE_TO_MANY, 'storage' => 'Storage_Db_Abstract_TestcaseStorage', 'fkToSelf' => 'test_id' ]
    ];
}

class Storage_Db_Abstract_TestcaseStorage extends AbstractStorage
{
    protected $tableName = 'testcases';
    protected $objectClass = Storage_Db_Abstract_TestcaseObject::class;

    protected $relations = [
        'tests' => [ 'type' => self::MANY_TO_ONE, 'storage' => 'Storage_Db_Abstract_TestStorage', 'fkToOther' => 'test_id' ],
        'items' => [ 'type' => self::MANY_TO_MANY, 'storage' => 'Storage_Db_Abstract_ItemStorage', 'fkToSelf' => 'testcase_id', 'fkToOther' => 'item_id', 'linkTable' => 'testcase_has_items' ]
    ];
}

class Storage_Db_Abstract_ItemStorage extends AbstractStorage
{
    protected $tableName = 'items';
    protected $objectClass = Storage_Db_Abstract_ItemObject::class;

    protected $relations = [
        'testcases' => [ 'type' => self::MANY_TO_MANY, 'storage' => 'Storage_Db_Abstract_TestcaseStorage', 'fkToSelf' => 'item_id', 'fkToOther' => 'testcase_id', 'linkTable' => 'testcase_has_items' ]
    ];
}

class Db_AbstractStorageTest extends \PHPUnit_Extensions_Database_TestCase
{
    private $sharedPdo;
    private $container;

    public function __construct()
    {
        $config = new Config([
            'dependencies' => [
                'factories' => [
                    Generic::class => function ($container, $name) {
                        return new MySQL([ 'dbname' => 'framewub_test' ], 'framewub', 'fr4m3wu8');
                    },
                    Storage_Db_Abstract_TestStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_Abstract_TestStorage($container->get(Generic::class));
                        $storage->setContainer($container);
                        return $storage;
                    },
                    Storage_Db_Abstract_TestcaseStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_Abstract_TestcaseStorage($container->get(Generic::class));
                        $storage->setContainer($container);
                        return $storage;
                    },
                    Storage_Db_Abstract_ItemStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_Abstract_ItemStorage($container->get(Generic::class));
                        $storage->setContainer($container);
                        return $storage;
                    }
                ]
            ]
        ]);
        $this->container = new Container($config->dependencies);
        // $this->db = new MySQL([ 'dbname' => 'framewub_test' ], 'framewub', 'fr4m3wu8');
        $this->sharedPdo = $this->container->get(Generic::class)->getPdo();
    }

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        return $this->createDefaultDBConnection($this->sharedPdo, 'framewub_test');
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        return $this->createFlatXMLDataSet(dirname(__DIR__).'/data/related-seed.xml');
    }

    public function testConstruct()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);
    }

    public function testFind()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);

        // Find all
        $resultset = $storage->find();
        $this->assertInstanceOf(Resultset::class, $resultset);
        $this->assertEquals(3, $resultset->count());
        $obj = $resultset->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals(1, $obj->id);
        $this->assertEquals('First test', $obj->name);

        // Find with where
        $resultset = $storage->find([ 'created' => [ '$lt' => '2016-01-01 00:00:00' ] ]);
        $this->assertInstanceOf(Resultset::class, $resultset);
        $this->assertEquals(2, $resultset->count());
        $obj = $resultset->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals(1, $obj->id);
        $this->assertEquals('First test', $obj->name);

        // Find with order
        $resultset = $storage->find(null, 'id DESC');
        $this->assertInstanceOf(Resultset::class, $resultset);
        $this->assertEquals(3, $resultset->count());
        $obj = $resultset->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals(3, $obj->id);
        $this->assertEquals('Third test', $obj->name);
    }

    public function testFindOne()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);

        // Find one by ID
        $obj = $storage->findOne(1);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals(1, $obj->id);
        $this->assertEquals('First test', $obj->name);

        // Find one by conditions
        $obj = $storage->findOne([ 'name' => 'First test' ]);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals(1, $obj->id);
        $this->assertEquals('First test', $obj->name);
    }

    public function testInsert()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);

        $now = date('Y-m-d H:i:s');

        $id = $storage->insert([ 'id' => 4, 'name' => 'Fourth test', 'created' => $now ]);
        $this->assertEquals(4, $id);

        // Find one by ID
        $obj = $storage->findOne($id);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals(4, $obj->id);
        $this->assertEquals('Fourth test', $obj->name);
        $this->assertEquals($now, $obj->created);
    }

    public function testUpdate()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);

        $now = date('Y-m-d H:i:s');
        $id = $storage->insert([ 'name' => 'Next test', 'created' => $now ]);

        // Update by ID
        $now = date('Y-m-d H:i:s');
        $result = $storage->update([ 'name' => 'New test', 'modified' => $now ], $id);
        $this->assertEquals(1, $result);

        // Find one by ID
        $obj = $storage->findOne($id);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals('New test', $obj->name);
        $this->assertEquals($now, $obj->modified);

        // Update by conditions
        $now = date('Y-m-d H:i:s');
        $result = $storage->update([ 'name' => 'Modified test', 'modified' => $now ], [ 'name' => 'New test' ]);
        $this->assertEquals(1, $result);

        // Find one by ID
        $obj = $storage->findOne($id);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals('Modified test', $obj->name);
        $this->assertEquals($now, $obj->modified);
    }

    public function testDelete()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);

        // Delete by ID
        $now = date('Y-m-d H:i:s');
        $id = $storage->insert([ 'name' => 'Test to delete', 'created' => $now ]);
        $result = $storage->delete($id);
        $this->assertEquals(1, $result);

        $obj = $storage->findOne($id);
        $this->assertEquals(null, $obj);

        // Delete by conditions
        $now = date('Y-m-d H:i:s');
        $id = $storage->insert([ 'name' => 'Test to delete', 'created' => $now ]);
        $result = $storage->delete([ 'name' => 'Test to delete' ]);
        $this->assertEquals(1, $result);

        $obj = $storage->findOne($id);
        $this->assertEquals(null, $obj);
    }

    public function testSave()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);

        $now = date('Y-m-d H:i:s');

        // Save a new object
        $id = $storage->save([ 'name' => 'New test', 'created' => $now ]);
        $this->assertGreaterThan(3, $id);

        $obj = $storage->findOne($id);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals('New test', $obj->name);
        $this->assertEquals($now, $obj->created);

        // Update existing
        $id = $storage->save([ 'id' => $id, 'name' => 'Modified test', 'modified' => $now ]);

        $obj = $storage->findOne($id);
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $obj);
        $this->assertEquals('Modified test', $obj->name);
        $this->assertEquals($now, $obj->modified);
    }

    public function testFindByRelated()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestcaseStorage::class);
        $testcases = $storage->findByTest(1);

        $this->assertInstanceOf(Resultset::class, $testcases);

        $tc = $testcases->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestcaseObject::class, $tc);
        $this->assertEquals('First test, first testcase', $tc->name);

        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);
        $tests = $storage->findByTestcase(1);

        $this->assertInstanceOf(Resultset::class, $tests);

        $test = $tests->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $test);
        $this->assertEquals('First test', $test->name);

        $storage = $this->container->get(Storage_Db_Abstract_ItemStorage::class);
        $items = $storage->findByTestcase(3);

        $this->assertInstanceOf(Resultset::class, $items);

        $item = $items->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_ItemObject::class, $item);
        $this->assertEquals('Second item', $item->name);
    }

    public function testFindRelated()
    {
        $storage = $this->container->get(Storage_Db_Abstract_TestStorage::class);
        $testcases = $storage->findTestcases(1);

        $this->assertInstanceOf(Resultset::class, $testcases);

        $tc = $testcases->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestcaseObject::class, $tc);
        $this->assertEquals('First test, first testcase', $tc->name);

        $storage = $this->container->get(Storage_Db_Abstract_TestcaseStorage::class);
        $tests = $storage->findTest(1);

        $this->assertInstanceOf(Resultset::class, $tests);

        $test = $tests->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_TestObject::class, $test);
        $this->assertEquals('First test', $test->name);

        $items = $storage->findItems(3);

        $this->assertInstanceOf(Resultset::class, $items);

        $item = $items->fetchOne();
        $this->assertInstanceOf(Storage_Db_Abstract_ItemObject::class, $item);
        $this->assertEquals('Second item', $item->name);
    }

    public function testAddRelated()
    {
        $testStorage = $this->container->get(Storage_Db_Abstract_TestStorage::class);
        $testcaseStorage = $this->container->get(Storage_Db_Abstract_TestcaseStorage::class);

        $testStorage->addTestcase(1, 9);
        $testcase = $testcaseStorage->findOne([ 'id' => 9 ]);
        $this->assertEquals(1, $testcase->test_id);

        $testcaseStorage->addTest(8, 1);
        $testcase = $testcaseStorage->findOne([ 'id' => 8 ]);
        $this->assertEquals(1, $testcase->test_id);

        $testcaseStorage->addItem(1, 2);
        $sql = "SELECT * FROM testcase_has_items WHERE testcase_id = 1 AND item_id = 2";
        $db = $this->container->get(Generic::class);
        $stmt = $db->execute($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $result);
        $this->assertEquals(1, $result['testcase_id']);
        $this->assertEquals(2, $result['item_id']);
    }

    public function testUnlinkRelated()
    {
        $testStorage = $this->container->get(Storage_Db_Abstract_TestStorage::class);
        $testcaseStorage = $this->container->get(Storage_Db_Abstract_TestcaseStorage::class);

        $testStorage->unlinkTestcase(1, 2);
        $testcase = $testcaseStorage->findOne([ 'id' => 2 ]);
        $this->assertNull($testcase->test_id);

        $testcaseStorage->unlinkTest(3, 1);
        $testcase = $testcaseStorage->findOne([ 'id' => 3 ]);
        $this->assertNull($testcase->test_id);

        $testcaseStorage->unlinkItem(2, 1);
        $sql = "SELECT * FROM testcase_has_items WHERE testcase_id = 2 AND item_id = 1";
        $db = $this->container->get(Generic::class);
        $stmt = $db->execute($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertFalse($result);
    }
}
