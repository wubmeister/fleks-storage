<?php

use PHPUnit\Framework\TestCase;

use Fleks\Storage\BranchedObject;
use Fleks\Storage\StorageInterface;
use Fleks\Storage\BranchedTrait;


class Storage_MockBranchedStorage implements StorageInterface
{
    use BranchedTrait;

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

class Storage_Branched_MockStorageObject extends BranchedObject
{
    public function __construct(StorageInterface $storage, $data = [])
    {
        parent::__construct($storage);

        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

class BranchedTraitTest extends TestCase
{
    public function testGetLeftRightKeys()
    {
        $storage = new Storage_MockBranchedStorage();

        $this->assertEquals('left', $storage->getLeftKey());
        $this->assertEquals('right', $storage->getRightKey());
    }

    public function testTreeFromData()
    {
        $storage = new Storage_MockBranchedStorage();

        $data = [
            0 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 1, 'right' => 16, 'name' => 'Root' ]),
            1 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 2, 'right' => 3, 'name' => 'Sub 1' ]),
            2 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 5, 'right' => 8, 'name' => 'Sub 2' ]),
            3 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 6, 'right' => 7, 'name' => 'Sub 2.1' ]),
            4 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 8, 'right' => 13, 'name' => 'Sub 3' ]),
            5 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 9, 'right' => 10, 'name' => 'Sub 3.1' ]),
            6 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 11, 'right' => 12, 'name' => 'Sub 3.2' ]),
            7 => new Storage_Branched_MockStorageObject($storage, [ 'left' => 14, 'right' => 15, 'name' => 'Sub 4' ])
        ];

        $tree = $storage->getTreeFromData($data);
        $this->assertEquals($data[0], $tree);
        $children = $tree->getChildren();
        $this->assertEquals($data[1], $children[0]);
        $this->assertEquals($data[2], $children[1]);
        $this->assertEquals($data[4], $children[2]);
        $this->assertEquals($data[7], $children[3]);
        // $subChildren = $children[1]->getChildren();
        // $this->assertEquals($data[3], $subChildren[0]);
    }
}
