<?php

/**
 * Interface for node (tree) storage classes
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage;

interface NodeStorageInterface extends StorageInterface
{
    /**
     * This method should return a tree of nodes with the root node bing the one
     * identified by $id. The tree should not go deeper than the specified $depth.
     *
     * @param mixed $rootId The ID of the root node
     * @param int $depth The maximum depth of the tree. If omitted, there will be no maximum depth to the tree
     * @return Fleks\Storage\NodeInterface
     */
    public function fetchTree($rootId, int $depth);

    /**
     * This method should append the specified child node to the specified parent node
     *
     * @param int|Fleks\Storage\NodeInterface $child The child node or its ID in the storage
     * @param int|Fleks\Storage\NodeInterface $parent The parent node or its ID in the storage
     * @return static Return $this to provide chaining;
     */
    public function appendNode($child, $parent);

    /**
     * This method should insert the specified node before the specified reference node
     *
     * @param int|Fleks\Storage\NodeInterface $child The child node or its ID in the storage
     * @param int|Fleks\Storage\NodeInterface $ref The reference node or its ID in the storage
     * @return static Return $this to provide chaining;
     */
    public function insertBefore($child, $ref);
}
