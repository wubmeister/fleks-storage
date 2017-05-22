<?php

/**
 * Interface for tree nodes coming from storages
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage;

interface NodeInterface
{
    /**
     * This method should append the specified child node to this node
     *
     * @param int|Fleks\Storage\NodeInterface $child The child node or its ID in the storage
     * @param int|Fleks\Storage\NodeInterface $parent The parent node or its ID in the storage
     * @return static Return $this to provide chaining;
     */
    public function appendChild($child);

    /**
     * This method should insert the specified node before the specified reference node
     *
     * @param int|Fleks\Storage\NodeInterface $child The child node or its ID in the storage
     * @param int|Fleks\Storage\NodeInterface $ref The reference node or its ID in the storage
     * @return static Return $this to provide chaining;
     */
    public function insertBefore($child, $ref);
}
