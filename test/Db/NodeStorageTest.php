<?php

use PHPUnit\Framework\TestCase;

use Fleks\Util\Config;
use Fleks\Util\Container;
use Fleks\Db\Generic;
use Fleks\Db\MySQL;
use Fleks\Storage\Db\NodeObject;
use Fleks\Storage\Db\NodeStorage;

class Storage_Db_NodeStorage_TestStorage extends NodeStorage
{
    protected $tableName = 'nodes';
    protected $leftKey = 'left_bound';
    protected $rightKey = 'right_bound';
    // protected $objectClass = Storage_Db_Abstract_TestObject::class;
}

class NodeStorageTest extends \PHPUnit_Extensions_Database_TestCase
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
                    Storage_Db_NodeStorage_TestStorage::class => function ($container, $name) {
                        $storage = new Storage_Db_NodeStorage_TestStorage($container->get(Generic::class));
                        $storage->setContainer($container);
                        return $storage;
                    }
                ]
            ]
        ]);

        $this->container = new Container($config->dependencies);
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
        return $this->createFlatXMLDataSet(dirname(__DIR__).'/data/nodes-seed.xml');
    }

    public function testObject()
    {
        $storage = $this->container->get(Storage_Db_NodeStorage_TestStorage::class);

        $obj = $storage->findOne(1);
        $this->assertInstanceOf(NodeObject::class, $obj);
    }

    public function testFetchTree()
    {
        $storage = $this->container->get(Storage_Db_NodeStorage_TestStorage::class);

        $root = $storage->fetchTree(5);
        $this->assertEquals('Sub 3', $root->title);
        $this->assertTrue($root->hasChildren());
        $children = $root->getChildren();
        $this->assertEquals('Sub 3.1', $children[0]->title);
        $this->assertEquals('Sub 3.2', $children[1]->title);
    }

    public function testFetchRootTree()
    {
        $storage = $this->container->get(Storage_Db_NodeStorage_TestStorage::class);

        $root = $storage->fetchTree();
        $this->assertEquals('Root', $root->title);
        $this->assertTrue($root->hasChildren());
        $children = $root->getChildren();

        $this->assertEquals('Sub 1', $children[0]->title);
        $this->assertEquals('Sub 2', $children[1]->title);
        $this->assertEquals('Sub 3', $children[2]->title);
        $this->assertEquals('Sub 4', $children[3]->title);

        $this->assertTrue($children[1]->hasChildren());
        $subChildren = $children[1]->getChildren();
        $this->assertEquals('Sub 2.1', $subChildren[0]->title);

        $this->assertTrue($children[2]->hasChildren());
        $subChildren = $children[2]->getChildren();
        $this->assertEquals('Sub 3.1', $subChildren[0]->title);
        $this->assertEquals('Sub 3.2', $subChildren[1]->title);

    }

    public function testAppendNode()
    {
        $storage = $this->container->get(Storage_Db_NodeStorage_TestStorage::class);

        $id = $storage->insert([ 'title' => 'Sub 2.2' ]);
        $storage->appendNode($id, 2);

        $tree = $storage->fetchTree(2);
        $children = $tree->getChildren();
        $this->assertEquals(2, count($children));
        $this->assertEquals('Sub 2.2', $children[1]->title);
    }
}
