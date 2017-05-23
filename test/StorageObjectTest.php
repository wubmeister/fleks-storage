<?php

use PHPUnit\Framework\TestCase;

use Fleks\Storage\StorageInterface;
use Fleks\Storage\StorageObject;

class Storage_MockStorage implements StorageInterface
{
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

class StorageObjectTest extends TestCase
{
    public function testConstruct()
    {
    	$storage = new Storage_MockStorage();

        $obj = new StorageObject($storage);
        $obj->foo = 'bar';
        $obj->lorem = 'ipsum';

        $this->assertEquals('bar', $obj->foo);
        $this->assertEquals('ipsum', $obj->lorem);
    }

    public function testSave()
    {
    	$storage = new Storage_MockStorage();

        $obj = new StorageObject($storage);
        $obj->foo = 'bar';
        $obj->lorem = 'ipsum';
        $id = $obj->save();

        $this->assertEquals(1, $id);
    }
}
