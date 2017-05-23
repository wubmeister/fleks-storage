<?php

use PHPUnit\Framework\TestCase;

use Fleks\Storage\BranchedObject;
use Fleks\Storage\BranchedTrait;
use Fleks\Storage\StorageInterface;

class Storage_BO_MockBranchedStorage implements StorageInterface
{
    use BranchedTrait;

    public function __construct() {
        $this->leftKey = 'left_bound';
        $this->rightKey = 'right_bound';
    }

    public function save(array $values)
    {
        return isset($values['id']) ? $values['id'] : 1;
    }

    public function find($where = null, $order = null)
    {
        return [];
    }

    public function findOne($idOrWhere = null)
    {
        return [];
    }

    public function insert(array $values){}
    public function update(array $values, $idOrWhere = null){}
    public function delete($idOrWhere = null){}
}

class BranchedObjectTest extends TestCase
{
    public function testAppendChild()
    {
        $storage = new Storage_BO_MockBranchedStorage();

        $obj = new BranchedObject($storage);
        $obj->name = 'root';
        $obj2 = new BranchedObject($storage);
        $obj2->name = 'sub';
        $obj3 = new BranchedObject($storage);
        $obj3->name = 'sub2';

        $obj->appendChild($obj2);
        $obj->appendChild($obj3);

        $children = $obj->getChildren();

        $this->assertEquals($obj2, $children[0]);
        $this->assertEquals($obj3, $children[1]);
    }

    public function testResetTreeLayout()
    {
        $storage = new Storage_BO_MockBranchedStorage();

        $obj = new BranchedObject($storage);
        $obj->name = 'root';
        $obj2 = new BranchedObject($storage);
        $obj2->name = 'sub1';
        $obj3 = new BranchedObject($storage);
        $obj3->name = 'sub2';
        $obj4 = new BranchedObject($storage);
        $obj4->name = 'sub3';

        $obj->appendChild($obj2);
        $obj->appendChild($obj3);
        $obj3->appendChild($obj4);

        $obj->resetTreeLayout();

        $this->assertEquals(1, $obj->left_bound);
        $this->assertEquals(8, $obj->right_bound);
        $this->assertEquals(2, $obj2->left_bound);
        $this->assertEquals(3, $obj2->right_bound);
        $this->assertEquals(4, $obj3->left_bound);
        $this->assertEquals(7, $obj3->right_bound);
        $this->assertEquals(5, $obj4->left_bound);
        $this->assertEquals(6, $obj4->right_bound);
    }

    public function testHasChildren()
    {
        $storage = new Storage_BO_MockBranchedStorage();

        $obj = new BranchedObject($storage);
        $obj2 = new BranchedObject($storage);

        $this->assertFalse($obj->hasChildren());
        $obj->appendChild($obj2);
        $this->assertTrue($obj->hasChildren());
    }

    public function testGetLeftRight()
    {
        $storage = new Storage_BO_MockBranchedStorage();

        $obj = new BranchedObject($storage);
        $obj->left_bound = '1';
        $obj->right_bound = '12';

        $this->assertEquals(1, $obj->getLeft());
        $this->assertInternalType('int', $obj->getLeft());
        $this->assertEquals(12, $obj->getRight());
        $this->assertInternalType('int', $obj->getRight());
    }
}
