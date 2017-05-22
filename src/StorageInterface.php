<?php

/**
 * Interface for all storage classes
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage;

/**
 * Storage interface
 */
interface StorageInterface
{
    /**
     * Finds rows in the table matching the specified set of conditions.
     *
     * @param array $where
     *   OPTIONAL. The conditions. If no conditions are specified, all rows in
     *   the table are fetched
     * @param string|array $order
     *   OPTIONAL. The order column(s)
     *
     * @return Fleks\Storage\Db\Resultset
     *   The resultset with the result
     */
    public function find($where = null, $order = null);

    /**
     * Finds a single row in the table matching the specified ID or set of
     * conditions.
     *
     * @param int|string|array $idOrWhere
     *   An ID or set of conditions
     *
     * @return Fleks\Storage\StorageObject
     *   The resulting storage object
     */
    public function findOne($idOrWhere = null);

    /**
     * Inserts a row in the table
     *
     * @param array $values
     *   The values to insert
     *
     * @return int
     *   The ID of the newly inserted item or null on failure
     */
    public function insert(array $values);

    /**
     * Updates a row in the table
     *
     * @param array $values
     *   The values to update
     * @param int|string|array
     *   OPTIONAL. An ID or a set of conditions for the update query. If nothing
     *   is specified, all rows in the table will be updated.
     *
     * @return int
     *   The number of affected rows
     */

    public function update(array $values, $idOrWhere = null);
    /**
     * Inserts or updates a set of values
     *
     * @param array $values
     *   The values to save
     *
     * @return int|string
     *   A unique identifier for the saved element
     */
    public function save(array $values);

    /**
     * Deletes a row from the table
     *
     * @param int|string|array
     *   An ID or a set of conditions for the update query. If nothing is
     *   specified, all rows in the table will be updated.
     *
     * @return int
     *   The number of affected rows
     */
    public function delete($idOrWhere = null);
}
