<?php

/**
 * Select Query builder class
 *
 * @package activerecord.query
 * @author Integry Systems
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
	public function setFilter(ARFilter $filter)
	{
		$this->filter = $filter;
	}

	/**
	 * @return ARSelectFilter
	 */
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
	 *
	 * @return string Table alias name or false if couldn't join
	 **/
	public function joinTable($tableName, $mainTableName, $tableJoinFieldName, $mainTableJoinFieldName, $tableAliasName = false)
	{
		if (!$tableAliasName)
		{
		  	$tableAliasName = $tableName;
		}

		// check if not already joined
		foreach ($this->getJoinsByClassName($tableName) as $join)
		{
			if (($mainTableJoinFieldName == $join['mainTableJoinFieldName']) && ($tableJoinFieldName == $join['tableJoinFieldName']))
			{
				return false;
			}
		}

		if(!(isset($this->joinList[$tableAliasName]) || isset($this->tableList[$tableAliasName])))
		{
			$this->joinList[$tableAliasName] = array(
				"tableName" => $tableName,
				"mainTableName" => $mainTableName,
				"tableJoinFieldName" => $tableJoinFieldName,
				"mainTableJoinFieldName" => $mainTableJoinFieldName,
				"tableAliasName" => $tableAliasName
			);

			return true;
		}

		return false;
	}

	public function getJoinsByClassName($className)
	{
		$ret = array();

		foreach ($this->joinList as $join)
		{
			if ($className == $join['tableName'])
			{
				$ret[] = $join;
			}
		}

		return $ret;
	}

	/**
	 * Includes table to a table list
	 *
	 * @param string $tableName
	 */
	public function includeTable($tableName)
	{
		$this->tableList[$tableName] = true;
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
	private function createStatementBody()
	{
		$filterFieldList = ($this->filter instanceof ARSelectFilter) ? $this->filter->getFieldList() : array();
		$fields = array_merge($this->fieldList, $filterFieldList);

		$tableAliases = array();

		$fieldListStr = "";
		$preparedFieldList = array();

		if (empty($fields))
		{
			$fieldStr = "*";
		}
		else
		{
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
					$field .= ' AS "' . $fieldInfo['fieldNameInResult'] . '"';
				}
				$preparedFieldList[] = $field;
			}
		}

		$fieldListStr = implode(", ", $preparedFieldList);

		$tableListStr = implode(", ", array_keys($this->tableList));

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
				  	$alias = ' AS `' . $joinItem['tableAliasName'] . '`';
				  	$tableName = $joinItem['tableAliasName'];
				}
				else
				{
				  	$alias = '';
				  	$tableName = $joinItem['tableName'];
				}

				if(empty($joinItem['tableAliasName']))
				{
					$tableAliases[$joinItem['tableName']] = $joinItem['tableName'];
				}
				else if(!isset($tableAliases[$joinItem['tableName']]))
				{
					$tableAliases[$joinItem['tableName']] = $joinItem['tableAliasName'];
				}

				$preparedJoinList[] = "LEFT JOIN `".$joinItem['tableName'].'`'.$alias." ON ".(isset($tableAliases[$joinItem['mainTableName']]) ? $tableAliases[$joinItem['mainTableName']] : $joinItem['mainTableName']).".".$joinItem['mainTableJoinFieldName']." = ".$tableName.".".$joinItem['tableJoinFieldName'].'';
			}
			$joinListStr = implode(" ", $preparedJoinList);
		}

		return "SELECT ".$fieldListStr." FROM ".$tableListStr." ".$joinListStr;
	}

	public function createString()
	{
		$sql = $this->createStatementBody();

		if ($this->filter != null)
		{
			$sql .= $this->filter->createString();
		}

		return $sql;
	}

	public function getPreparedStatement(ConnectionCommon $conn)
	{
		$body = $this->createStatementBody();
		$values = array();

		if (is_null($this->filter))
		{
			return $conn->prepareStatement($body);
		}

		$prepared = $this->filter->createPreparedStatement();

		preg_match_all('/\?\?\?([a-z0-9]*)@@@/', $prepared['sql'], $matches);
		$values = $matches[1];

		$statement = $conn->prepareStatement($body . preg_replace('/\?\?\?([a-z0-9]*)@@@/', '?', $prepared['sql']));

		foreach ($values as $key => $id)
		{
			$key++;
			$value = $prepared['values'][$id];

			if ('int' == $value['type'])
			{
				$statement->setInt($key, $value['value']);
			}
			else if ('timestamp' == $value['type'])
			{
				$statement->setTimestamp($key, $value['value']);
			}
			else
			{
				$statement->setString($key, $value['value']);
			}
		}

		return $statement;
	}

	public function __toString()
	{
		return $this->createString();
	}
}

?>
