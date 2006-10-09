<?php

/**
 * Class for table structure representation (also known as schema)
 *
 * @package activerecord.schema
 */
class ARSchema
{
	/**
	 * Table name
	 *
	 * @var string
	 */
	private $tableName = "";
	private $fieldList = array();
	private $foreignKeyList = array();
	private $primaryKeyList = array();

	public function registerField(ARField $schemaField)
	{
		$this->fieldList[$schemaField->getName()] = $schemaField;

		if ($schemaField instanceof ARForeignKey)
		{
			$this->foreignKeyList[$schemaField->getName()] = $schemaField;
		}
		if ($schemaField instanceof ARPrimaryKey)
		{
			$this->primaryKeyList[$schemaField->getName()] = $schemaField;
		}
	}

	/**
	 * Checks if a given field exists in schema
	 *
	 * @param sting $fieldName
	 * @return bool
	 */
	public function fieldExists($fieldName)
	{
		if (!empty($this->fieldList[$fieldName]))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets a table name which is represented by this schema
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->tableName;
	}

	/**
	 * Sets a table name
	 *
	 * @param string $tableName
	 */
	public function setName($tableName)
	{
		$this->tableName = $tableName;
	}

	/**
	 * Gets a schema field name
	 *
	 * @param string $name
	 * @return ARSchemaField Schema field instance
	 */
	public function getField($name)
	{
		return $this->fieldList[$name];
	}

	/**
	 * Gets a list of all schema fields
	 *
	 * @param int $fieldClass
	 * @return ARSchemaField[]
	 */
	public function getFieldList()
	{
		return $this->fieldList;
	}

	/**
	 * Gets a list of foreign keys defined for this schema
	 *
	 * @return ARPrimaKey[]
	 */
	public function getForeignKeyList()
	{
		return $this->foreignKeyList;
	}

	/**
	 * Gets alist o primary key fields defined for this schema
	 *
	 * @return ARForeigKey[]
	 */
	public function getPrimaryKeyList()
	{
		return $this->primaryKeyList;
	}

	/**
	 * Creatres a sting of enumerated fields
	 *
	 * @param string $prefix Prefix to add for each field (field renaming)
	 * @param string $separator Field separator
	 */
	public function enumerateFields($prefix = "", $separator = ",")
	{
		$fieldList = array();
		foreach($this->fieldList as $name => $field)
		{
			$fieldStr = $this->getName().".".$name;
			if (!empty($prefix))
			{
				$fieldStr .= " AS ".$prefix.$name;
			}
			$fieldList[] = $fieldStr;
		}
		return implode($separator, $fieldList);
	}

	/**
	 * Checks if this schema is a valid one.
	 * Valid schema must have a name assigned
	 */
	public function isValid()
	{
		if (!empty($this->tableName))
		{
			return true;
		}
		else
		{
			false;
		}
	}

	public function toArray()
	{
		$fieldArray = array();
		foreach($this->fieldList as $field)
		{
			$fieldArray[] = $field->getName();
		}
		return $fieldArray;
	}
}

?>
