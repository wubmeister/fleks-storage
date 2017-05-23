<?php

/**
 * Class to represent a branched storage in the database
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage\Db;

use Fleks\Db\Query\Select;

class NodeStorage extends AbstractStorage
{
    /**
     * The key in the data that represents the left bound of a node in a nested
     * set.
     *
     * @var string
     */
    protected $leftKey = 'left';

    /**
     * The key in the data that represents the right bound of a node in a nested
     * set.
     *
     * @var string
     */
    protected $rightKey = 'right';

    /**
     * The class of the returned object rows
     *
     * @var string
     */
    protected $objectClass = NodeObject::class;

    /**
     * Returns the key in the data that represents the left bound of a node in a
     * nested set.
     *
     * @return string
     */
    public function getLeftKey()
    {
        return $this->leftKey;
    }

    /**
     * Returns the key in the data that represents the right bound of a node in
     * a nested set.
     *
     * @return string
     */
    public function getRightKey()
    {
        return $this->rightKey;
    }

    /**
     * Builds a tree from a nested set
     *
     * @param array|Resultset $data
     *   The nested set
     *
     * @return StorageObject
     *   The root
     */
    public function getTreeFromData($data)
    {
        $root = $top = new NodeObject($this);
        $right = -1;
        $topStack = [];

        $leftKey = $this->leftKey;
        $rightKey = $this->rightKey;

        foreach ($data as $item) {
            $top->appendChild($item);
            if ($item->getRight() > $item->getLeft() + 1) {
                $topStack[] = $top;
                $top = $item;
            } else if ($top->getRight() !== null && $item->getRight() == $top->getRight() - 1) {
                $oldRight = $item->getRight();
                while ($oldRight == $top->getRight() - 1) {
                    $top = array_pop($topStack);
                    $oldRight = $top->getRight();
                }
            }
        }

        if (count($root->getChildren()) == 1) {
            $root = $root->getChildren()[0];
        }
        return $root;
    }

    /**
     * Fetches a tree by it's root node id
     *
     * @param mixed $id
     *   OPTIONAL. The ID of the root node. If no ID is given, the full tree
     *   from the table will be read.
     *
     * @return NodeObject
     *   The root node with all of its children
     */
    public function fetchTree($id = null, $where = null)
    {
        $select = new Select($this->db);

        if ($id !== null) {
            $childLeft = 'child.' . $this->leftKey;
            $parentLeft = $this->db->quoteIdentifier('parent.' . $this->leftKey);
            $parentRight = $this->db->quoteIdentifier('parent.' . $this->rightKey);

            $select
                ->from([ 'parent' => $this->tableName, 'child' => $this->tableName ], 'child.*')
                ->where([ 'parent.id' => $id ])
                ->where([ $childLeft => [ '$between' => [ $parentLeft, $parentRight ] ] ]);

            if ($where) {
                $fixWhere = [];
                foreach ($where as $key => $value) {
                    $key = 'child.'.$key;
                    $fixWhere[$key] = $value;
                }
                $select->where($fixWhere);
            }

        } else {
            $childLeft = $this->leftKey;
            $select->from($this->tableName);
            if ($where) {
                $select->where($where);
            }
        }
        $select->order($childLeft);

        $resultSet = $this->findBySelect($select);

        return $this->getTreeFromData($resultSet);
    }

    /**
     * Appends a persistent node as direct child to another, updating the
     * left/right bounds.
     *
     * @param mixed $nodeId
     *   The ID of the node to append
     * @param mixed $parentId
     *   The ID of the parent node, to which the node should be appended
     */
    public function appendNode($nodeId, $parentId)
    {

        $node = $this->findOne($nodeId);
        $parent = $this->findOne($parentId);

        if ($node && $parent) {
            $queries = [];

            // Check if node has bounds
            if ($node->getLeft()) {
                // If so, first make its bounds negative
                $nodeRight = $node->getRight();
                $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = -`{$this->leftKey}`, `{$this->rightKey}` = -`{$this->rightKey}` " .
                    "WHERE `{$this->leftKey}` BETWEEN " . $node->getLeft() . " AND " . $node->getRight();
                // Then shift everything between parent's right and node's orginal right (inclusive) by the size of the node
                $size = $node->getSize();
                if ($node->getLeft() > $parent->getRight()) {
                    $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = `{$this->leftKey}` + {$size} WHERE `{$this->leftKey}` BETWEEN " . $parent->getRight() . " AND " . $node->getRight();
                    $queries[] = "UPDATE `{$this->tableName}` SET `{$this->rightKey}` = `{$this->rightKey}` + {$size} WHERE `{$this->rightKey}` BETWEEN " . $parent->getRight() . " AND " . $node->getRight();
                } else {
                    $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = `{$this->leftKey}` - {$size} WHERE `{$this->leftKey}` BETWEEN " . $node->getRight() . " AND " . $parent->getRight();
                    $queries[] = "UPDATE `{$this->tableName}` SET `{$this->rightKey}` = `{$this->rightKey}` - {$size} WHERE `{$this->rightKey}` BETWEEN " . $node->getRight() . " AND " . $parent->getRight();
                }
                // Then update the negative bounds of the node
                $shift = $node->getLeft() - $parent->getRight();
                $shiftStr = $shift < 0 ? '- ' . (-$shift) : '+ ' . $shift;
                $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = `{$this->leftKey}` {$shiftStr}, `{$this->rightKey}` = `{$this->rightKey}` {$shiftStr} WHERE `{$this->leftKey}` BETWEEN -" . $node->getRight() . " AND -" . $node->getLeft();
                // Then make the bounds of the node postive again
                $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = -`{$this->leftKey}`, `{$this->rightKey}` = -`{$this->rightKey}` " .
                    "WHERE `{$this->leftKey}` BETWEEN " . (-$node->getRight() + $shift) . " AND " . (-$node->getLeft() + $shift);
            } else {
                // If not, shift everything from parent's right to the right by the size of the node (= 2)
                $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = `{$this->leftKey}` + 2 WHERE `{$this->leftKey}` >= " . $node->getRight();
                $queries[] = "UPDATE `{$this->tableName}` SET `{$this->rightKey}` = `{$this->rightKey}` + 2 WHERE `{$this->rightKey}` >= " . $node->getRight();
                // Then update the bounds of the node
                $left = $parent->getRight();
                $right = $left + 1;
                $queries[] = "UPDATE `{$this->tableName}` SET `{$this->leftKey}` = {$left}, `{$this->rightKey}` = {$right} WHERE `id` = {$node->id}";
            }

            $this->db->commitTransaction($queries);
        }
    }
}
