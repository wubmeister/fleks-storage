<?php

/**
 * Class to represent a single item fetched from a storage
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage;

use PDO;
use ArrayAccess;

/**
 * Storage object
 */
class StorageObject implements ArrayAccess
{
    /**
     * The data attributes of the object
     *
     * @var array
     */
    protected $data = [];

    /**
     * The storage this object comes from
     *
     * @var Fleks\Storage\StorageInterface
     */
    protected $storage;

    /**
     * StorageObject constructor
     *
     * @param Fleks\Storage\StorageInterface $storage
     *   The storage this object comes from
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Sets a data attribute
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Gets a data attribute
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Uses the storage object to save this object
     *
     * @return mixed
     *   A unique identifier for the object
     */
    public function save()
    {
        return $this->storage->save($this->data);
    }

    /**
     * Converts the object into an array
     *
     * @return array
     *   The array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Checks if an offset exists
     *
     * @param mixed $offset The offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Gets the value at an offset
     *
     * @param mixed $offset The offset
     * @return mixed The value
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * Sets the value at an offset
     *
     * @param mixed $offset The offset
     * @param mixed $value The value
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Clears the value at an offset and removes the offset
     *
     * @param mixed $offset The offset
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
}
