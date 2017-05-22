<?php

/**
 * Extends the generic storage class with methods to handle relations between tables
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage\Db;

use RuntimeException;
use Fleks\Util;
use Fleks\Storage\StorageObject as BaseStorageObject;

class StorageObject extends BaseStorageObject
{
    /**
     * Handles all 'fetch*', 'add*' and 'remove*' function calls.
     *
     * @param string $name
     *   The function name
     * @param array $args
     *   The arguments
     *
     * @return mixed
     *   Whatever the sub-function returns
     */
    public function __call($name, $args)
    {
        if ($this->storage instanceof AbstractStorage) {
            if (substr($name, 0, 5) == 'fetch' && strlen($name) > 5) {
                $relName = lcfirst(substr($name, 5));
                $this->{$relName} = $this->storage->findRelated($relName, $this->id, count($args) > 0 ? $args[0] : null, count($args) > 1 ? $args[1] : null);
                return;
            } else if (substr($name, 0, 3) == 'add' && strlen($name) > 3) {
                $relName = Util::getPlural(lcfirst(substr($name, 3)));
                return $this->storage->addRelated($relName, $this->id, $args[0], count($args) > 1 ? $args[1] : []);
            } else if (substr($name, 0, 6) == 'unlink' && strlen($name) > 6) {
                $relName = Util::getPlural(lcfirst(substr($name, 6)));
                return $this->storage->unlinkRelated($relName, $this->id, $args[0]);
            }
        }

        throw new RuntimeException("Method '{$name}' doesn't exist");
    }
}
