<?php

/**
 * ActiveRecord set container/manager
 *
 * @package activerecord
 * @author Integry Systems  
 */
class ARSet implements IteratorAggregate, Serializable
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

	/**
	 * Creates an empty record set (record container)
	 *
	 * @param ARFilter $recordSetFilter Filter that was used to create a record set
	 */
	public function __construct(ARSelectFilter $recordSetFilter = null)
	{
		$this->filter = $recordSetFilter;
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
		unset($this->data[$recordIndex]);
		$this->data = array_values($this->data);
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
	 * Creates an array representing a record set data
	 *
	 * @param bool $recursive Convert to array all objects recursively?
	 * @see ActiveRecord::toArray()
	 * @return array
	 */
	public function toArray($force = true)
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
		return $this->data[$recordIndex];
	}

	public function size()
	{
		return count($this->data);
	}

	public function setTotalRecordCount($count)
	{
		$this->totalRecordCount = $count;
	}

	public function getTotalRecordCount()
	{
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
}

?>
