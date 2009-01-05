<?php

/**
 * ActiveRecord set container/manager
 *
 * @package activerecord
 * @author Integry Systems
 */
class ARSet implements IteratorAggregate, Serializable, Countable
{
	/**
	 * Record list
	 *
	 * @var ActiveRecord[]
	 */
	private $data = array();

	/**
	 * Filter that was being used to get this record set
	 *
	 * @var ARFilter
	 */
	private $filter = null;

	private $totalRecordCount = null;

	private $counterQuery;

	private $db;

	/**
	 * Creates an empty record set (record container)
	 *
	 * @param ARFilter $recordSetFilter Filter that was used to create a record set
	 */
	public function __construct(ARSelectFilter $recordSetFilter = null)
	{
		$this->filter = $recordSetFilter;
	}

	public static function buildFromArray($array)
	{
		$set = new ARSet();
		foreach ($array as $record)
		{
			$set->add($record);
		}

		return $set;
	}

	/**
	 * Adds a record to a record set
	 *
	 * @param ActiveRecord $record
	 */
	public function add(ActiveRecord $record)
	{
		$this->data[] = $record;
	}

	/**
	 * Merge two record sets
	 *
	 * @param ActiveRecord $record
	 */
	public function merge(ARSet $set)
	{
		$this->data = array_merge($this->data, $set->getData());
	}

	/**
	 * Shift a record from the record set
	 *
	 * @return ActiveRecord $record
	 */
	public function shift()
	{
		return array_shift($this->data);
	}

	public function unshift(ActiveRecord $record)
	{
		array_unshift($this->data, $record);
	}

	/**
	 * Removes a record from record set
	 *
	 * @param ActiveRecord $record
	 */
	public function remove($recordIndex)
	{
		if ($recordIndex instanceof ActiveRecord)
		{
			foreach ($this->data as $key => $record)
			{
				if ($record === $recordIndex)
				{
					$recordIndex = $key;
					break;
				}
			}

			if ($recordIndex instanceof ActiveRecord)
			{
				return null;
			}
		}

		unset($this->data[$recordIndex]);
		$this->data = array_values($this->data);

		return true;
	}

	/**
	 * Required definition of interface IteratorAggregate
	 *
	 * @return Iterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->data);
	}

	/**
	 * Required definition of interface Countable
	 *
	 * @return int
	 */
	public function count()
	{
		return $this->size();
	}

	/**
	 * Creates an array representing a record set data
	 *
	 * @param bool $recursive Convert to array all objects recursively?
	 * @see ActiveRecord::toArray()
	 * @return array
	 */
	public function toArray($force = false)
	{
		$recordSetArray = array();
		foreach($this->data as $record)
		{
			$recordSetArray[] = $record->toArray($force);
		}
		return $recordSetArray;
	}

	/**
	 * Creates an array representing a record set data (without referenced records)
	 *
	 * @see ActiveRecord::toFlatArray()
	 * @return array
	 */
	public function toFlatArray()
	{
		$recordSetArray = array();
		foreach($this->data as $record)
		{
			$recordSetArray[] = $record->toFlatArray();
		}
		return $recordSetArray;
	}

	/**
	 * Gets a record instance by index (starting from 0)
	 *
	 * @param int $recordIndex
	 * @return ActiveRecord
	 */
	public function get($recordIndex)
	{
		return isset($this->data[$recordIndex]) ? $this->data[$recordIndex] : null;
	}

	public function size()
	{
		return count($this->data);
	}

	public function setTotalRecordCount($count)
	{
		$this->totalRecordCount = $count;
	}

	public function setCounterQuery($query, $db)
	{
		$this->counterQuery = $query;
		$this->db = $db;
	}

	public function getTotalRecordCount()
	{
		if ($this->counterQuery)
		{
			$result = $this->counterQuery->executeQuery();
			$result->next();

			$resultData = $result->getRow();
			$this->setTotalRecordCount($resultData['totalCount']);
			$this->counterQuery = null;
		}

		if (!empty($this->totalRecordCount))
		{
			return $this->totalRecordCount;
		}
		else
		{
			return $this->size();
		}
	}

	/**
	 * gets a filter that was used for creating a record set
	 *
	 * @return ARSelectFilter
	 */
	public function getFilter()
	{
		return $this->filter;
	}

	public function removeRecord(ActiveRecord $removeRecord)
	{
		$i = 0;
		foreach($this as $record)
		{
			if($record === $removeRecord)
			{
				$this->remove($i);
				return true;
			}
			$i++;
		}
		return false;
	}

	public function getData()
	{
		return $this->data;
	}

	public function getRecordIDs()
	{
		$ids = array();
		foreach ($this as $record)
		{
			$ids[] = $record->getID();
		}

		return $ids;
	}

	public function getIDMap()
	{
		$map = array();
		foreach ($this as $record)
		{
			$map[$record->getID()] = $record;
		}

		return $map;
	}

	public function extractField($field)
	{
		$extract = array();
		foreach ($this as $record)
		{
			$extract[$record->getID()] = $record->getFieldValue($field);
		}

		return $extract;
	}

	/**
	 *
	 */
	public function extractReferencedItemSet($key, $class = 'ARSet')
	{
		$set = new $class();
		foreach ($this as $record)
		{
			if ($record->$key && $record->$key->get())
			{
				$set->add($record->$key->get());
			}
		}

		return $set;
	}

	protected function getRecordClass()
	{
		return substr(get_class($this), -3);
	}

	public function saveToFile($filePath)
	{
		file_put_contents($filePath, '<?php return unserialize(' . var_export(serialize($this), true) . '); ?>');
	}

	public function serialize()
	{
		$serialized = array('data' => $this->data);
		return serialize($serialized);
	}

	public function unserialize($serialized)
	{
		$array = unserialize($serialized);
		$this->data = $array['data'];
	}

	public function __destruct()
	{
		$this->filter = null;
		//logDestruct($this);
	}
}

?>
