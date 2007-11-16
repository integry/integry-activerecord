<?php

require_once("filter/ARFilter.php");

/**
 * Filter for selecting a record set
 *
 * @package activerecord.query.filter
 * @author Integry Systems
 */
class ARSelectFilter extends ARFilter
{
	const ORDER_ASC = "ASC";
	const ORDER_DESC = "DESC";

	/**
	 * Offset in a record set that should be returned by applying a filter
	 *
	 * @var int
	 */
	private $recordOffset = 0;

	/**
	 * Limit of records in a record set that will be returned by applying this filter
	 *
	 * When limit is set to 0, it is considered that record set size will be unlimited
	 *
	 * @var int
	 */
	private $recordLimit = 0;

	/**
	 * A list of fields to ORDER BY
	 *
	 * @var array
	 */
	private $fieldListForOrder = array();

	/**
	 * Record ordering type
	 *
	 * @var string
	 */
	private $orderType = self::ORDER_ASC;

	/**
	 * A list of fields to GROUP BY
	 *
	 * @var array
	 */
	private $fieldListForGroup = array();

	/**
	 * A list of additional fields to select
	 *
	 * @var array
	 */
	private $fieldList = array();

	/**
	 * Condition for HAVING clause
	 *
	 * @var array
	 */
	private $havingCondition;

	/**
	 * Creates a string by using filter data
	 *
	 * @return string
	 */
	public function createString()
	{
		$result = parent::createString();

		$params = array();
		$params[] = $this->createGroupString();
		$params[] = $this->createHavingString();
		$params[] = $this->createOrderString();

		if ($this->recordLimit != 0)
		{
			$params[] = " LIMIT ".$this->recordOffset.", ".$this->recordLimit;
		}

		$result .= implode(' ', $params);
					
		return $result;
	}

	/**
	 * Sets a limit of record count in a record set
	 *
	 * @param int $recordLimit
	 * @param int $recordOffset
	 */
	public function setLimit($recordLimit, $recordOffset = 0)
	{
		$this->recordLimit = $recordLimit;
		$this->recordOffset = $recordOffset;
	}

	/**
	 * Gets a record limit of record set
	 *
	 * @return int
	 */
	public function getLimit()
	{
		return $this->recordLimit;
	}

	/**
	 * Gets an offset value in a record set
	 *
	 * @return int
	 */
	public function getOffset()
	{
		return $this->recordOffset;
	}

	/**
	 * Sets record ordering by a given field name and order type
	 *
	 * @param string $fieldName
	 * @param string $orderType
	 */
	public function setOrder(ARFieldHandleInterface $fieldHandle, $orderType = self::ORDER_ASC)
	{
		$this->fieldListForOrder[$fieldHandle->toString()] = $orderType;
	}

	/**
	 * Sets record grouping by a given field name
	 *
	 * @param string $fieldName
	 */
	public function setGrouping(ARFieldHandleInterface $fieldHandle)
	{
		$this->fieldListForGroup[$fieldHandle->toString()] = $fieldHandle;
	}
	
	public function getGroupingFields()
	{
		return $this->fieldListForGroup;
	}
	
	/**
	 * Creates an "ORDER BY" statement string
	 *
	 * @return string
	 */
	public function createOrderString()
	{
		if (!empty($this->fieldListForOrder))
		{
			$orderList = array();
			foreach($this->fieldListForOrder as $fieldName => $order)
			{
				$orderList[] = $fieldName." ".$order;
			}
			return " ORDER BY " . implode(", ", $orderList);
		}
	}

	/**
	 * Creates "GROUP BY" statement string
	 *
	 * @return string
	 */
	public function createGroupString()
	{
		if ($this->fieldListForGroup)
		{
			return " GROUP BY " . implode(", ", array_keys($this->fieldListForGroup)); 
		}
	}

	/**
	 * Creates a textual filter representation
	 *
	 * This string might be used as a part of SQL query
	 *
	 */
	public function createHavingString()
	{
		if ($this->havingCondition != null)
		{
			return " HAVING " . $this->havingCondition->createChain();
		}
	}

	/**
	 * Returns an array of field ordering
	 *
	 * @return array
	 */
	public function getFieldOrder()
	{
		return $this->fieldListForOrder;
	}

	public function getOrderType()
	{
		return $this->orderType;
	}

	/**
	 * Merges two filters (filter supplied as parameter overwrites order and limit
	 * params of this filter)
	 *
	 * @param ARSelectFilter $filter
	 */
	public function merge(ARSelectFilter $filter)
	{
		if ($filter->isConditionSet())
		{
			$this->mergeCondition($filter->getCondition());
		}

		if ($filter->isHavingConditionSet())
		{
			$this->mergeHavingCondition($filter->getHavingCondition());
		}

		$this->setFieldOrder($filter->getFieldOrder());
		$this->setLimit($filter->getLimit(), $filter->getOffset());
		
		$groupings = $filter->getGroupingFields();
		$this->fieldListForGroup = array_merge($this->getGroupingFields(), $groupings);
				
		$joins = $filter->getJoinList();
		$this->joinList = array_merge($this->joinList, $joins);
		
		$fields = $filter->getFieldList();
		$this->fieldList = array_merge($this->fieldList, $fields);
	}
	
	public function mergeHavingCondition(Condition $cond)
	{
		if ($this->havingCondition != null)
		{
			$this->havingCondition->addAND($cond);
		}
		else
		{
			$this->havingCondition = $cond;
		}
	}	
	
	public function setHavingCondition(Condition $cond)
	{
		$this->havingCondition = $cond;
	}	

	public function getHavingCondition(Condition $cond)
	{
		return $this->havingCondition;
	}	
	
	public function isHavingConditionSet()
	{
		return ($this->havingCondition instanceof Condition);
	}
	
	public function addField($fieldName, $tableName = null, $fieldNameInResult = null)
	{
		$this->fieldList[] = array("fieldName" => $fieldName, "tableName" => $tableName, "fieldNameInResult" => $fieldNameInResult);
	}
		
	public function getFieldList()
	{
	  	return $this->fieldList;
	}

	public function removeFieldList()
	{
		$this->fieldList = array();		
	}

	public function resetOrder()
	{
		$this->fieldListForOrder = array();		
	}
	
	/**
	 * Sets a "bulk" ordering by passing an array of field to order by
	 *
	 */
	private function setFieldOrder($fieldList)
	{
		$this->fieldListForOrder = $fieldList;
	}
	
}

?>
