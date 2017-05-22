<?php

/**
 * Abstract database storage class
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage\Db;

use PDO;
use InvalidArgumentException;

use Psr\Container\ContainerInterface;

use Fleks\Util;
use Fleks\Container;
use Fleks\Db\Generic as GenericDb;
use Fleks\Storage\StorageInterface;
use Fleks\Db\Query\AbstractQuery;
use Fleks\Db\Query\Select;
use Fleks\Db\Query\Insert;
use Fleks\Db\Query\Update;
use Fleks\Db\Query\Delete;

/**
 * Abstract database storage class
 */
class AbstractStorage implements StorageInterface
{
    /**
     * Defines a one-to-many relationship
     */
    const ONE_TO_MANY = 1;

    /**
     * Defines a many-to-one relationship
     */
    const MANY_TO_ONE = 2;

    /**
     * Defines a many-to-many relationship
     */
    const MANY_TO_MANY = 3;

    /**
     * The table name
     *
     * @var string
     */
    protected $tableName;

    /**
     * The class of the returned object rows
     *
     * @var string
     */
    protected $objectClass = StorageObject::class;

    /**
     * The database adapter
     *
     * @var Fleks\Db\Generic
     */
    protected $db;

    /**
     * Service container to locate related storages
     *
     * @var Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * The relations for this storage
     *
     * @var array
     */
    protected $relatons = [];

    /**
     * Flag to see if this this table has a 'site_id' column
     * @var bool
     */
    protected $storeSiteId = false;

    /**
     * Flag to see if this this table has a 'created' column
     * @var bool
     */
    protected $createdStamp = false;

    /**
     * Flag to see if this this table has a 'modified' column
     * @var bool
     */
    protected $modifiedStamp = null;

    /**
     * Flag to see if this this table has a 'recycled' column
     * @var bool
     */
    protected $recycledStamp = null;

    /**
     * The site
     *
     * @var int
     */
    protected $site;

    public function __construct(GenericDb $db, $site)
    {
        $this->db = $db;
        $this->site = $site;

        $sql = "SHOW COLUMNS FROM `{$this->tableName}`";
        $stmt = $this->db->execute($sql);
        while ($row = $stmt->fetch()) {
            if ($row['Field'] == 'site_id') {
                $this->storeSiteId = true;
            }
            else if ($row['Field'] == 'created') {
                $this->createdStamp = true;
            }
            else if ($row['Field'] == 'modified') {
                $this->createdStamp = true;
            }
            else if ($row['Field'] == 'recycled') {
                $this->createdStamp = true;
            }
        }
    }

    /**
     * Gets the site object
     *
     * @return Zend\Config $site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Sets the service container to locate related storages
     *
     * @param Psr\Container\ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Prepares a PDO statement with bind values
     *
     * @param Fleks\Db\Query\AbstractQuery $query
     *   The SQL query
     *
     * @return PDOStatement
     *   The prepared PDO statement
     */
    protected function prepare(AbstractQuery $query)
    {
        $statement = $this->db->prepare($query, $query->getBind());

        return $statement;
    }

    /**
     * Finds multiple records based on a select query
     *
     * @param Fleks\Db\Query\Select|string $select
     *   The select query
     *
     * @return Fleks\Storage\Db\Resultset
     *   The resultset with the result
     */
    protected function findBySelect($select)
    {
        $resultset = new Resultset($select, $this);

        if ($this->objectClass) {
            $resultset->setObjectClass($this->objectClass);
        }

        return $resultset;
    }

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
    public function find($where = null, $order = null)
    {
        $select = new Select($this->db);
        $select->from($this->tableName);

        if ($where) {
            $select->where($where);
        }
        if ($order) {
            $select->order($order);
        }

        if ($this->storeSiteId) {
            $select->where([ 'site_id' => $this->site->id ]);
        }
        if ($this->recycledStamp) {
            $select->where([ 'recycled' => null ]);
        }

        return $this->findBySelect($select);
    }

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
    public function findOne($idOrWhere = null)
    {
        $select = new Select($this->db);
        $select->from($this->tableName);

        if (is_array($idOrWhere)) {
            $select->where($idOrWhere);
        } else {
            $select->where([ 'id' => $idOrWhere ]);
        }

        if ($this->storeSiteId) {
            $select->where([ 'site_id' => $this->site->id ]);
        }
        if ($this->recycledStamp) {
            $select->where([ 'recycled' => null ]);
        }

        $resultset = new Resultset($select, $this);
        if ($this->objectClass) {
            $resultset->setObjectClass($this->objectClass);
        }

        return $resultset->fetchOne();
    }

    /**
     * Inserts a row in the table
     *
     * @param array $values
     *   The values to insert
     *
     * @return int
     *   The ID of the newly inserted item or null on failure
     */
    public function insert(array $values)
    {
        if ($this->storeSiteId && !isset($values['site_id'])) {
            $values['site_id'] = $this->site->id;
        }
        if ($this->createdStamp && !isset($values['created'])) {
            $values['created'] = date('Y-m-d H:i:s');
            $values['created_by'] = 1;
        }

        $insert = new Insert($this->db);
        $insert->into($this->tableName)->values($values);

        $statement = $this->prepare($insert);

        $id = null;
        if ($statement->execute()) {
            $id = $this->db->getPdo()->lastInsertId();
        }

        return $id;
    }

    /**
     * Performs a DELETE or UPDATE, based on the passed argument, and returns
     * the number of affected rows
     *
     * @param Fleks\Db\Query\AbstractQuery $query
     *   The Delete or Update query
     * @param int|string|array
     *   An ID or a set of conditions for the update query. If nothing is
     *   specified, all rows in the table will be updated.
     *
     * @return int
     *   The number of affected rows
     */
    protected function deleteOrUpdate(AbstractQuery $query, &$idOrWhere)
    {
        if ($idOrWhere) {
            if (is_array($idOrWhere)) {
                $query->where($idOrWhere);
            } else {
                $query->where([ 'id' => $idOrWhere ]);
            }
        }

        $statement = $this->prepare($query);

        $numRows = 0;
        if ($statement->execute()) {
            $numRows = $statement->rowCount();
        }

        return $numRows;
    }

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
    public function update(array $values, $idOrWhere = null)
    {
        if ($this->storeSiteId && !isset($values['site_id'])) {
            $values['site_id'] = $this->site->id;
        }
        if ($this->modifiedStamp && !isset($values['modified'])) {
            $values['modified'] = date('Y-m-d H:i:s');
            $values['modified_by'] = 1;
        }

        $update = new Update($this->db);
        $update->table($this->tableName)->values($values);

        return $this->deleteOrUpdate($update, $idOrWhere);
    }

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
    public function delete($idOrWhere = null)
    {
        $delete = new Delete($this->db);
        $delete->from($this->tableName);

        return $this->deleteOrUpdate($delete, $idOrWhere);
    }

    /**
     * Inserts or updates a row in the table. If there is an ID in the values,
     * it will try to update the row with that ID.
     *
     * @param array $values
     *   The values to save
     *
     * @return int|string
     *   The ID of the saved element
     */
    public function save(array $values)
    {
        if (isset($values['id'])) {
            $id = $values['id'];
            unset($values['id']);
            $this->update($values, $id);
        } else {
            $id = $this->insert($values);
        }

        return $id;
    }

    /**
     * Gets the PDO object
     *
     * @return PDO
     */
    public function getPdo()
    {
        return $this->db->getPdo();
    }

    /**
     * Finds all records in the storage related to an object with the given ID
     * and the given relationship
     *
     * @param string $relation
     *   The key of the relation, as it is defined in the $relations property.
     * @param mixed $id
     *   The ID of the related object
     *
     * @return Fleks\Storage\Db\Resultset
     *   The resultset with the results
     */
    public function findByRelated(string $relation, $otherId)
    {
        if (isset($this->relations[$relation])) {
            extract($this->relations[$relation]);

            $select = new Select($this->db);
            $select->from($this->tableName);

            switch ($type) {
                case self::MANY_TO_ONE:
                    $select->where([ $fkToOther => $otherId ]);
                    break;

                case self::ONE_TO_MANY:
                    $storageObj = $this->container->get($storage);
                    $fk = $storageObj->tableName.'.id';
                    $select
                        ->from($storageObj->tableName, [])
                        ->joinLeft($this->tableName, $this->tableName.'.id = '.$storageObj->tableName.'.'.$fkToSelf)
                        ->where([ $fk => $otherId ]);
                    break;

                case self::MANY_TO_MANY:
                    $fk = $linkTable.'.'.$fkToOther;
                    $select
                        ->from($linkTable, [])
                        ->joinLeft($this->tableName, $this->tableName.'.id = '.$linkTable.'.'.$fkToSelf)
                        ->where([ $fk => $otherId ]);
                    break;

                default:
                    throw new InvalidArgumentException("Relationship type not implemented");

            }

            return $this->findBySelect($select);
        }

        return null;
    }

    /**
     * Finds all records in related to an object in this with the given ID
     * and the given relationship
     *
     * @param string $relation
     *   The key of the relation, as it is defined in the $relations property.
     * @param mixed $id
     *   The ID of the object in this storage
     *
     * @return Fleks\Storage\Db\Resultset|null
     *   The resultset with the results of null if nothing was found
     */
    public function findRelated(string $relation, $id, $where = null, $order = null)
    {
        if (!isset($this->relations[$relation])) {
            $relation = Util::getPlural($relation);
        }

        if (isset($this->relations[$relation])) {
            extract($this->relations[$relation]);

            $storageObj = $this->container->get($storage);
            $select = new Select($this->db);
            $select->from($storageObj->tableName);

            switch ($type) {
                case self::ONE_TO_MANY:
                    $select->where([ $fkToSelf => $id ]);
                    break;

                case self::MANY_TO_ONE:
                    $fk = $this->tableName.'.id';
                    $select
                        ->from($this->tableName, [])
                        ->joinLeft($storageObj->tableName, $storageObj->tableName.'.id = '.$this->tableName.'.'.$fkToOther)
                        ->where([ $fk => $id ]);
                    break;

                case self::MANY_TO_MANY:
                    $fk = $linkTable.'.'.$fkToSelf;
                    $select
                        ->from($linkTable, [])
                        ->joinLeft($storageObj->tableName, $storageObj->tableName.'.id = '.$linkTable.'.'.$fkToOther)
                        ->where([ $fk => $id ]);
                    break;

                default:
                    throw new InvalidArgumentException("Relationship type not implemented");

            }

            if ($where) {
                $select->where($where);
            }
            if ($order) {
                $select->order($order);
            }

            return $storageObj->findBySelect($select);
        }

        return null;
    }

    /**
     * Adds a related record to a record from this storage by creating or
     * updating a relation
     *
     * @param string $relation
     *   The key of the relation, as it is defined in the $relations property.
     * @param mixed $id
     *   The ID of the object in this storage
     * @param mixed $otherId
     *   The ID of the object in the other storage
     * @param array $extraData
     *   Extra data to insert into the link table for many-to-many relationships
     *
     * @return Fleks\Storage\Db\Resultset|null
     *   The resultset with the results of null if nothing was found
     */
    public function addRelated(string $relation, $id, $otherId, $extraData = [])
    {
        if (isset($this->relations[$relation])) {
            extract($this->relations[$relation]);

            switch ($type) {
                case self::ONE_TO_MANY:
                    $storageObj = $this->container->get($storage);
                    $query = new Update($this->db);
                    $query
                        ->table($storageObj->tableName)
                        ->values([ $fkToSelf => $id ])
                        ->where([ 'id' => $otherId ]);
                    break;

                case self::MANY_TO_ONE:
                    $query = new Update($this->db);
                    $query
                        ->table($this->tableName)
                        ->values([ $fkToOther => $otherId ])
                        ->where([ 'id' => $id ]);
                    break;

                case self::MANY_TO_MANY:
                    $extraData[$fkToSelf] = $id;
                    $extraData[$fkToOther] = $otherId;
                    $query = new Insert($this->db);
                    $query->ignore()->into($linkTable)->values($extraData);
                    break;

                default:
                    throw new InvalidArgumentException("Relationship type not implemented");
            }

            $this->db->execute($query, $query->getBind());
        }
    }

    /**
     * Unlinks a related record to a record from this storage by deleting or
     * updating a relation
     *
     * @param string $relation
     *   The key of the relation, as it is defined in the $relations property.
     * @param mixed $id
     *   The ID of the object in this storage
     * @param mixed $otherId
     *   The ID of the object in the other storage
     *
     * @return Fleks\Storage\Db\Resultset|null
     *   The resultset with the results of null if nothing was found
     */
    public function unlinkRelated(string $relation, $id, $otherId, $extraData = [])
    {
        if (isset($this->relations[$relation])) {
            extract($this->relations[$relation]);

            switch ($type) {
                case self::ONE_TO_MANY:
                    $storageObj = $this->container->get($storage);
                    $query = new Update($this->db);
                    $query
                        ->table($storageObj->tableName)
                        ->values([ $fkToSelf => null ])
                        ->where([ 'id' => $otherId, $fkToSelf => $id ]);
                    break;

                case self::MANY_TO_ONE:
                    $query = new Update($this->db);
                    $query
                        ->table($this->tableName)
                        ->values([ $fkToOther => null ])
                        ->where([ 'id' => $id, $fkToOther => $otherId ]);
                    break;

                case self::MANY_TO_MANY:
                    $query = new Delete($this->db);
                    $query
                        ->from($linkTable)
                        ->where([ $fkToSelf => $id, $fkToOther => $otherId ]);
                    break;

                default:
                    throw new InvalidArgumentException("Relationship type not implemented");
            }

            $this->db->execute($query, $query->getBind());
        }
    }

    /**
     * Generates a Select object for this table
     *
     * @return Fleks\Db\Query\Select The select object
     */
    public function select()
    {
        $select = new Select($this->db);
        $select->from($this->tableName);
        return $select;
    }

    /**
     * Gets the database adapter
     *
     * @return Fleks\Db\Generic The database adapter
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Handles all 'findBy*', 'find*', 'add*' and 'remove*' function calls.
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
        if (substr($name, 0, 6) == 'findBy' && strlen($name) > 6) {
            $relName = Util::getPlural(lcfirst(substr($name, 6)));
            return $this->findByRelated($relName, $args[0]);
        } else if (substr($name, 0, 4) == 'find' && strlen($name) > 4) {
            $relName = lcfirst(substr($name, 4));
            return $this->findRelated($relName, $args[0], count($args) > 1 ? $args[1] : null, count($args) > 2 ? $args[2] : null);
        } else if (substr($name, 0, 3) == 'add' && strlen($name) > 3) {
            $relName = Util::getPlural(lcfirst(substr($name, 3)));
            return $this->addRelated($relName, $args[0], $args[1], count($args) > 2 ? $args[2] : []);
        } else if (substr($name, 0, 6) == 'unlink' && strlen($name) > 6) {
            $relName = Util::getPlural(lcfirst(substr($name, 6)));
            return $this->unlinkRelated($relName, $args[0], $args[1]);
        }

        throw new InvalidArgumentException("Method '{$name}' doesn't exist");
    }
}
