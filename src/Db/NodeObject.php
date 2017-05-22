<?php

/**
 * Class to represent branched (tree model) storage objects
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage\Db;

use Fleks\Storage\NodeInterface;

class BranchedObject extends StorageObject implements NodeInterface
{
    /**
     * All the child nodes
     *
     * @var array
     */
    protected $children = [];

    /**
     * Appends a child to the node
     *
     * @param static $child
     */
    public function appendChild($child)
    {
        $this->children[] = $child;
    }

    /**
     * Returns all the children in an array
     *
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Checks if this object has any children
     *
     * @return bool
     */
    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    /**
     * Gets the left bound of this node
     *
     * @return int
     */
    public function getLeft()
    {
        $leftKey = $this->storage->getLeftKey();
        return (int)$this->{$leftKey};
    }

    /**
     * Gets the left bound of this node
     *
     * @return int
     */
    public function getRight()
    {
        $rightKey = $this->storage->getRightKey();
        return (int)$this->{$rightKey};
    }

    /**
     * Calculates the size of the node, i.e. the difference between the left and
     * right bound plus one. This will caclulate the size by looking at the
     * current state of the tree.
     *
     * @return int
     */
    protected function calcSize() {
        $size = 2; // Including left and right
        foreach ($this->children as $child) {
            $size += $child->calcSize();
        }

        return $size;
    }

    /**
     * Gets the size of the node, i.e. the difference between the left and
     * right bound plus one. This will calculate the size by subtracting the
     * left bound from the right bound and adding 1
     *
     * @return int
     */
    public function getSize() {
        return 1 + $this->getRight() - $this->getLeft();
    }

    /**
     * Sets the left and right bounds according to how the nodes are ordered
     * at the moment.
     *
     * @param int $startLeft
     *   OPTIONAL. The left bound of the root. Defaults to 1.
     */
    public function resetTreeLayout(int $startLeft = 1)
    {
        $leftKey = $this->storage->getLeftKey();
        $rightKey = $this->storage->getRightKey();

        $this->{$leftKey} = $startLeft;
        $this->{$rightKey} = $startLeft + $this->calcSize() - 1;

        $startLeft++;
        foreach ($this->children as $child) {
            $child->resetTreeLayout($startLeft);
            $startLeft += $child->calcSize();
        }
    }

    public function toArray($childrenKey = 'children')
    {
        $data = $this->data;
        $data[$childrenKey] = [];
        foreach ($this->getChildren() as $child) {
            $data[$childrenKey][] = $child->toArray($childrenKey);
        }
        return $data;
    }
}
