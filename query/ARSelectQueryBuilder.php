<?php

/**
 * Select Query builder class
 *
 * @package activerecord.query
 */
class ARSelectQueryBuilder
{
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
	public function setFilter(ARSelectFilter $filter)
	{
		$this->filter = $filter;
	}

	public function getFilter()
	{
		if ($this->filter == null)
		{
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
	 * @param string $tableAliasName	Necessary when joining the same table more than one time (LEFT JOIN sometable AS table_1 ON ... LEFT JOIN sometable AS table_2 ON ...)
	 */
	public function joinTable($tableName, $mainTableName, $tableJoinFieldName, $mainTableJoinFieldName, $tableAliasName = '')
	{
		$this->joinList[] = array("tableName" => $tableName, 
								  "mainTableName" => $mainTableName, 
								  "tableJoinFieldName" => $tableJoinFieldName, 
								  "mainTableJoinFieldName" => $mainTableJoinFieldName,
								  "tableAliasName" => $tableAliasName
								  );
	}

	/**
	 * Includes table to a table list
	 *
	 * @param string $tableName
	 */
	public function includeTable($tableName)
	{
		$this->tableList[] = $tableName;
	}

	public function addField($fieldName, $tableName = null, $fieldNameInResult = null)
	{
		$this->fieldList[] = array("fieldName" => $fieldName, "tableName" => $tableName, "fieldNameInResult" => $fieldNameInResult);
	}

	public function removeFieldList()
	{
		$this->fieldList = array();
	}

	/**
	 * Creates a string representing SQL SELECT query
	 *
	 * @return string
	 */
	public function createString()
	{
		$filterFieldList = $this->filter->getFieldList();
		$fields = array_merge($this->fieldList, $filterFieldList);
		
		$fieldListStr = "";
		if (empty($fields))
		{
			$fieldStr = "*";
		}
		else
		{
			$preparedFieldList = array();
			foreach($fields as $fieldInfo)
			{
				$field = "";
				if (!empty($fieldInfo['tableName']))
				{
					$field = $fieldInfo['tableName'].".".$fieldInfo['fieldName'];
				}
				else
				{
					$field = $fieldInfo['fieldName'];
				}
				if (!empty($fieldInfo['fieldNameInResult']))
				{
					$field .= " AS ".$fieldInfo['fieldNameInResult'];
				}
				$preparedFieldList[] = $field;
			}
		}
		$fieldListStr = implode(", ", $preparedFieldList);

		$tableListStr = implode(", ", $this->tableList);
	
		// add joins from select filter
		$filterJoins = $this->filter->getJoinList();
		$joins = array_merge($this->joinList, $filterJoins);

		$joinListStr = "";
		if (!empty($joins))
		{
			$preparedJoinList = array();
			foreach($joins as $joinItem)
			{
				if (!empty($joinItem['tableAliasName']))
				{
				  	$alias = ' AS ' . $joinItem['tableAliasName'] . ' ';
				  	$tableName = $joinItem['tableAliasName'];
				}
				else
				{
				  	$alias = '';
				  	$tableName = $joinItem['tableName'];
				}
				$preparedJoinList[] = "LEFT JOIN ".$joinItem['tableName'].$alias." ON ".$joinItem['mainTableName'].".".$joinItem['mainTableJoinFieldName']." = ".$tableName.".".$joinItem['tableJoinFieldName'];
			}
			$joinListStr = implode(" ", $preparedJoinList);
		}

		$selectQueryString = "SELECT ".$fieldListStr." FROM ".$tableListStr." ".$joinListStr;
		if ($this->filter != null)
		{
			$selectQueryString .= $this->filter->createString();
		}//echo $selectQueryString . '<br><br><br>';
		return $selectQueryString;
	}
}

?>
