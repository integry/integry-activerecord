<?php

/**
 * Select Query builder class
 *
 * @package activerecord.query
 */
class ARSelectQueryBuilder {
	
	/**
	 * Enter description here...
	 *
	 * @var ARSelectFilter
	 */
	private $filter = null;
	
	/**
	 * List of tables to select data from
	 *
	 * @var array
	 */
	private $tableList = array();
	
	/**
	 * List of tables to join on a main table
	 *
	 * @var array
	 */
	private $joinList = array();
	
	/**
	 * Field list to include in a query result
	 *
	 * @var array
	 */
	private $fieldList = array();
	
	/**
	 * Sets a select filter
	 *
	 * @param ARSelectFilter $filter
	 */
	public function setFilter(ARSelectFilter $filter) {
		$this->filter = $filter;
	}
	
	public function getFilter() {
		if ($this->filter == null) {
			$this->filter = new ARSelectFilter();
		}
		return $this->filter;
	}
	
	/**
	 * Joins table by using supplied params
	 *
	 * @param string $tableName
	 * @param string $mainTableName
	 * @param string $tableJoinFieldName
	 * @param string $mainTableJoinFieldName
	 */
	public function joinTable($tableName, $mainTableName, $tableJoinFieldName, $mainTableJoinFieldName) {
		$this->joinList[] = array("tableName" => $tableName, "mainTableName" => $mainTableName, "tableJoinFieldName" => $tableJoinFieldName, "mainTableJoinFieldName" => $mainTableJoinFieldName);
	}
	
	/**
	 * Includes table to a table list
	 *
	 * @param string $tableName
	 */
	public function includeTable($tableName) {
		$this->tableList[] = $tableName;
	}
	
	public function addField($fieldName, $tableName = null, $fieldNameInResult = null) {
		$this->fieldList[] = array("fieldName" => $fieldName, "tableName" => $tableName, "fieldNameInResult" => $fieldNameInResult);
	}
	
	public function removeFieldList() {
		unset($this->fieldList);
	}
	
	/**
	 * Creates a string representing SQL SELECT query
	 *
	 * @return string
	 */
	public function createString() {
		
		$fieldListStr = "";
		if (empty($this->fieldList)) {
			$fieldStr = "*";
		} else {
			$preparedFieldList = array();
			foreach ($this->fieldList as $fieldInfo) {
				$field = "";
				if (!empty($fieldInfo['tableName'])) {
					$field = $fieldInfo['tableName'] . "." . $fieldInfo['fieldName'];
				} else {
					$field = $fieldInfo['fieldName'];
				}
				if (!empty($fieldInfo['fieldNameInResult'])) {
					$field .= " AS " . $fieldInfo['fieldNameInResult'];
				}
				$preparedFieldList[] = $field;
			}
		}
		$fieldListStr = implode(", ", $preparedFieldList);
		
		$tableListStr = implode(", ", $this->tableList);
		
		$joinListStr = "";
		if (!empty($this->joinList)) {
			$preparedJoinList = array();
			foreach ($this->joinList as $joinItem) {
				$preparedJoinList[] = "LEFT JOIN " . $joinItem['tableName'] . " ON " . $joinItem['mainTableName'] . "." . $joinItem['mainTableJoinFieldName'] . " = " . $joinItem['tableName'] . "." . $joinItem['tableJoinFieldName'];
			}
			$joinListStr = implode(" ", $preparedJoinList);
		}
		
		$selectQueryString = "SELECT " . $fieldListStr. " FROM " . $tableListStr . " " . $joinListStr;
		if ($this->filter != null) {
			$selectQueryString .= $this->filter->createString();
		}
		return $selectQueryString;
	}
}

?>