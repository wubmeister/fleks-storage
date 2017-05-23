<?php

use PHPUnit\Framework\TestCase;

use Fleks\Util\Config;
use Fleks\Util\Container;
use Fleks\Db\MySQL;
use Fleks\Storage\Db\StorageObject;
use Fleks\Storage\Db\Resultset;
use Fleks\Storage\Db\AbstractStorage;

class Storage_Db_Object_TestStorage extends AbstractStorage
{
    protected $tableName = 'tests';
    protected $objectClass = StorageObject::class;

    protected $relations = [
        'testcases' => [ 'type' => self::ONE_TO_MANY, 'storage' => 'Storage_Db_Object_TestcaseStorage', 'fkToSelf' => 'test_id' ]
    ];
}

class Storage_Db_Object_TestcaseStorage extends AbstractStorage
{
    protected $tableName = 'testcases';
    protected $objectClass = StorageObject::class;

    protected $relations = [
        'tests' => [ 'type' => self::MANY_TO_ONE, 'storage' => 'Storage_Db_Object_TestStorage', 'fkToOther' => 'test_id' ],
        'items' => [ 'type' => self::MANY_TO_MANY, 'storage' => 'Storage_Db_Object_ItemStorage', 'fkToSelf' => 'testcase_id', 'fkToOther' => 'item_id', 'linkTable' => 'testcase_has_items' ]
    ];
}

class Storage_Db_Object_ItemStorage extends AbstractStorage
{
    protected $tableName = 'items';
    protected $objectClass = StorageObject::class;

    protected $relations = [
        'testcases' => [ 'type' => self::MANY_TO_MANY, 'storage' => 'Storage_Db_Object_TestcaseStorage', 'fkToSelf' => 'item_id', 'fkToOther' => 'testcase_id', 'linkTable' => 'testcase_has_items' ]
    ];
}

class Db_StorageObjectTest extends \PHPUnit_Extensions_Database_TestCase
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
                    Storage_Db_Object_TestStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_Object_TestStorage($container->get(Generic::class));
                        $storage->setContainer($container);
                        return $storage;
                    },
                    Storage_Db_Object_TestcaseStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_Object_TestcaseStorage($container->get(Generic::class));
                        $storage->setContainer($container);
                        return $storage;
                    },
                    Storage_Db_Object_ItemStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_Object_ItemStorage($container->get(Generic::class));
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
        return $this->createFlatXMLDataSet(dirname(dirname(__DIR__)).'/data/related-seed.xml');
    }

    public function testFetchRelated()
    {
        $testStorage = $this->container->get(Storage_Db_Object_TestStorage::class);
        $test = $testStorage->findOne(1);
        $this->assertInstanceOf(StorageObject::class, $test);

        $test->fetchTestcases();
        $this->assertInstanceOf(Resultset::class, $test->testcases);
        $testcase = $test->testcases->fetchOne();
        $this->assertInstanceOf(StorageObject::class, $testcase);
        $this->assertEquals('First test, first testcase', $testcase->name);
    }

    public function testAddRelated()
    {
        $testStorage = $this->container->get(Storage_Db_Object_TestStorage::class);
        $testcaseStorage = $this->container->get(Storage_Db_Object_TestcaseStorage::class);

        $test = $testStorage->findOne(1);

        $test->addTestcase(4);

        $testcase = $testcaseStorage->findOne(4);

        $this->assertInstanceOf(StorageObject::class, $testcase);
        $this->assertEquals(1, $testcase->test_id);
    }

    public function testUnlinkRelated()
    {
        $testStorage = $this->container->get(Storage_Db_Object_TestStorage::class);
        $testcaseStorage = $this->container->get(Storage_Db_Object_TestcaseStorage::class);

        $test = $testStorage->findOne(1);

        $test->unlinkTestcase(3);

        $testcase = $testcaseStorage->findOne(3);

        $this->assertInstanceOf(StorageObject::class, $testcase);
        $this->assertNull($testcase->test_id);
    }
}
