<?php

require_once("filter/ARFilter.php");

/**
 * Filter for selecting a record set
 * 
 * @package activerecord.query.filter
 * @author Saulius Rupainis <saulius@integry.net>
 */
class ARSelectFilter extends ARFilter {
	
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
	 * A list of fields to order by
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
	 * Creates a string by using filter data
	 *
	 * @return string
	 */
	public function createString() {
		$result = parent::createString();
		if (!empty($this->fieldListForOrder)) {
			//$result .= " ORDER BY " . $this->fieldListForOrder . " " . $this->orderType;
			$result .= $this->createOrderString();
		}
		if ($this->recordLimit != 0) {
			$result .= " LIMIT " . $this->recordOffset . ", " . $this->recordLimit;
		}
		return $result;
	}
	
	/**
	 * Sets a limit of record count in a record set
	 *
	 * @param int $recordLimit
	 * @param int $recordOffset
	 */
	public function setLimit($recordLimit, $recordOffset = 0) {
		$this->recordLimit = $recordLimit;
		$this->recordOffset = $recordOffset;
	}
	
	/**
	 * Gets a record limit of record set
	 *
	 * @return int
	 */
	public function getLimit() {
		return $this->recordLimit;
	}
	
	/**
	 * Gets an offset value in a record set
	 *
	 * @return int
	 */
	public function getOffset() {
		return $this->recordOffset;
	}
	
	/**
	 * Sets record ordering by a given field name and order type
	 *
	 * @param string $fieldName
	 * @param string $orderType
	 */
	//public function setOrder($fieldName, $orderType = self::ORDER_ASC) {
	public function setOrder(ARFieldHandle $fieldHandle, $orderType = self::ORDER_ASC) {
		$this->fieldListForOrder[$fieldHandle->toString()] = $orderType;
		//$this->fieldListForOrder[$fieldName] = $orderType;
	}
	
	/**
	 * Creates an "ORDER BY" statement string
	 *
	 * @return string
	 */
	public function createOrderString() {
		
		$orderList = array();
		foreach ($this->fieldListForOrder as $fieldName => $order) {

			//$orderList[] = "`" . $fieldName . "` " . $order;
			$orderList[] =  $fieldName . " " . $order;
		}
		return " ORDER BY " . implode(", ", $orderList);
	}
	
	/**
	 * Returns an array of field ordering
	 *
	 * @return array
	 */
	public function getFieldOrder() {
		return $this->fieldListForOrder;
	}
	
	/**
	 * Sets a "bulk" ordering by passing an array of field to order by
	 *
	 */
	private function setFieldOrder($fieldList) {
		$this->fieldListForOrder = $fieldList;
	}
	
	public function getOrderType() {
		return $this->orderType;
	}
	
	/**
	 * Merges two filters (filter supplied as parameter overwrites order and limit 
	 * params of this filter)
	 *
	 * @param ARSelectFilter $filter
	 */
	public function merge(ARSelectFilter $filter) {
		
		if ($this->isConditionSet() && $filter->isConditionSet()) {
			$this->getCondition()->addAND($filter->getCondition());
		} else {
			if ($filter->isConditionSet()) {
				$this->setCondition($filter->getCondition());
			}
		}
		//$this->setOrder($filter->getOrder(), $filter->getOrderType());
		$this->setFieldOrder($filter->getFieldOrder());
		$this->setLimit($filter->getLimit(), $filter->getOffset());
	}
}

?>