<?php

/**
 * Class to represent a database search result
 *
 * @package    fleks-storage
 * @author     Wubbo Bos <wubbo@wubbobos.nl>
 * @copyright  Copyright (c) Wubbo Bos
 */

namespace Fleks\Storage\Db;

use PDO;
use PDOStatement;
use Iterator;
use Fleks\Storage\StorageObject;
use Fleks\Db\Query\Func;

/**
 * Abstract database storage class
 */
class Resultset implements Iterator
{
	/**
	 * The select query
	 *
	 * @var Fleks\Db\Query\Select
	 */
	protected $select;

	/**
	 * The object class
	 *
	 * @var string
	 */
	protected $objectClass;

	/**
	 * The PDO adapter
	 *
	 * @var PDO
	 */
	protected $pdo;

	/**
	 * The storage
	 *
	 * @var Fleks\Storage\Db\AbstractStorage
	 */
	protected $storage;

	/**
	 * The executed PDO statement
	 *
	 * @var PDOStatement
	 */
	protected $statement;

	/**
	 * The number of rows in this row set
	 *
	 * @var int
	 */
	protected $numRows = -1;

	/**
	 * The fetched objects so far
	 *
	 * @var array
	 */
	protected $fetched = [];

	/**
	 * The current iterator position
	 *
	 * @var int
	 */
	protected $currPos = 0;

	/**
	 * Initializes the resultset with a select object and a storage
	 *
	 * @param Fleks\Db\Query\Select $select
	 *   The select object, which can be modified for certain purposes.
	 * @param Fleks\Storage\Db\AbstractStorage
	 *   The storage from which the results come
	 */
	public function __construct($select, $storage)
	{
		$this->select = $select;
		$this->storage = $storage;
	}

	/**
	 * Sets the class name for the fetched objects
	 *
	 * @param string $class
	 *   The class name
	 */
	public function setObjectClass(string $class)
	{
		if (!class_exists($class)) {
			throw new \Exception("Class '{$class}' not found");
		}
		$this->objectClass = $class;
	}

	/**
	 * Prepares and executes the statement
	 */
	protected function prepare()
	{
		if (!$this->objectClass) {
			$this->objectClass = StorageObject::class;
		}

		$this->statement = $this->storage->getPdo()->prepare((string)$this->select);
		$params = $this->select->getBind();
		foreach ($params as $key => $value) {
			$this->statement->bindValue($key, $value);
		}

		$this->statement->setFetchMode(PDO::FETCH_CLASS, $this->objectClass, [ $this->storage ]);
		$this->statement->execute();
	}

	/**
	 * Fetches the object at the cursor and advances the cursor to the next result
	 *
	 * @return Fleks\Storage\StorageObject
	 *   The object at the cursor or null if there are no other objects to fetch
	 */
	public function fetchOne()
	{
		if (!$this->statement) {
			$this->prepare();
		}

		return $this->statement->fetch();
	}

	/**
	 * Fetches the object at the cursor and advances the cursor to the next result
	 *
	 * @return Fleks\Storage\StorageObject
	 *   The object at the cursor or null if there are no other objects to fetch
	 */
	public function fetchAll()
	{
		if (!$this->statement) {
			$this->prepare();
		}

		return $this->statement->fetchAll();
	}

	/**
	 * Returns the numer of items in the row set
	 *
	 * @return int
	 *   The number of rows
	 */
	public function count()
	{
		if ($this->numRows == -1) {
			$select = clone $this->select;
			$select->columns([ 'count' => new Func('COUNT(*)') ], true);

			$statement = $this->storage->getPdo()->prepare((string)$select);
			$params = $select->getBind();
			foreach ($params as $key => $value) {
				$statement->bindValue($key, $value);
			}
			$statement->execute();
			$row = $statement->fetch(PDO::FETCH_ASSOC);
			$this->numRows = $row ? $row['count'] : 0;
		}
		return $this->numRows;
	}

	/**
	 * Converts the entire resultset into an array
	 *
	 * @return array
	 *   The array
	 */
	public function toArray()
	{
		$result = [];
		foreach ($this as $row) {
			$result[] = $row->toArray();
		}

		return $result;
	}

	/**
	 * Maps results to an array, using the callback function
	 *
	 * @param callable $callback The callback function: function($item)
	 * @return array The resulting array
	 */
	public function map(callable $callback)
	{
		$result = [];
		foreach ($this as $row) {
			$result[] = $callback($row);
		}
		return $result;
	}

	/**
	 * The current item
	 *
	 * @return
	 *   The item at the cursor
	 */
	public function current()
	{
		if ($this->currPos >= count($this->fetched)) {
			$this->fetched[$this->currPos] = $this->fetchOne();
		}
		return $this->fetched[$this->currPos];
	}

	/**
	 * The key of current item
	 *
	 * @return
	 *   The key at the cursor
	 */
	public function key()
	{
		return $this->currPos;
	}

	/**
	 * Advance to the next item
	 */
	public function next()
	{
		$this->currObject = null;
		$this->currPos++;
	}

	/**
	 * Rewind to the beginning
	 */
	public function rewind()
	{
		$this->currPos = 0;
	}

	/**
	 * Check to see if the current position is valid
	 *
	 * @return bool
	 */
	public function valid()
	{
		if ($this->currPos >= count($this->fetched)) {
			$this->fetched[$this->currPos] = $this->fetchOne();
		}
		return $this->fetched[$this->currPos] ? true : false;
	}

	/**
	 * Fetches a paginated result, based on the number of items per page and the
	 * current page number
	 *
	 * @param int $itemsPerPage The maximum number of items per page
	 * @param int $currPage The current page
	 */
	public function getPagination(int $itemsPerPage, int $currPage = 1)
	{
		$numItems = $this->count();
		$pagination = new \Fleks\Pagination($itemsPerPage, $numItems, $currPage);
		$this->select->offset($pagination->firstItemIndex)->limit($pagination->itemsPerPage);
		$pagination->results = $this;

		return $pagination;
	}
}
