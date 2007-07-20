<?php

include_once("schema/ARField.php");
include_once("schema/ARSchemaDataType.php");

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

	private $fieldsByType = array();

	/**
	 *	Cache array of referenced schema instances for faster lookups
	 */
	private $referencedSchemaList = array();

	public function registerField(ARField $schemaField)
	{
		$name = $schemaField->getName();
        $this->fieldList[$name] = $schemaField;

		if ($schemaField instanceof ARForeignKey)
		{
			$this->foreignKeyList[$name] = $schemaField;
		}
		
		if ($schemaField instanceof ARPrimaryKey)
		{
			$this->primaryKeyList[$name] = $schemaField;
		}
		
		$this->fieldsByType[get_class($schemaField->getDataType())][$name] = $schemaField;
	}

	/**
	 * Checks if a given field exists in schema
	 *
	 * @param sting $fieldName
	 * @return bool
	 */
	public function fieldExists($fieldName)
	{
		return !empty($this->fieldList[$fieldName]);
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
		if (isset($this->fieldList[$name]))
		{
			return $this->fieldList[$name];		  
		}
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
	 * @return ARPrimaryKey[]
	 */
	public function getForeignKeyList()
	{
		return $this->foreignKeyList;
	}

	/**
	 * Gets a list of primary key fields defined for this schema
	 *
	 * @return ARForeignKey[]
	 */
	public function getPrimaryKeyList()
	{
		return $this->primaryKeyList;
	}

	/**
	 * Returns a list of ARArray schema fields
	 *
	 * @return ARField[]
	 */
	public function getFieldsByType($className)
	{
		return isset($this->fieldsByType[$className]) ? $this->fieldsByType[$className] : array();
	}
	
	/**
	 * Returns a list of ARArray schema fields
	 *
	 * @return ARField[]
	 * @todo remove
	 */
	public function getArrayFieldList()
	{
		return $this->getFieldsByType('ARArray');
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
	 * Returns an array of all directly and indirectly referenced schemas (infinite levels of referencing)
	 *
	 * @todo Think of a better way to handle circular references
	 */
	public function getReferencedSchemas($circularReference = false)
	{
		if (!$this->referencedSchemaList)
		{
			$ret = array();
			
			$referenceList = $this->getForeignKeyList();		
	
			foreach($referenceList as $name => $refField)
			{
				$foreignClassName = $refField->getForeignClassName();
				$refSchema = ActiveRecord::getSchemaInstance($foreignClassName);
				if ($this === $refSchema || $refSchema === $circularReference)
				{
				  	continue;
				}
				
				if(!isset($ret[$refField->getReferenceName()])) $ret[$refField->getReferenceName()] = array();
				$ret[$refField->getReferenceName()][] = $refSchema;
				
                $sub = $refSchema->getReferencedSchemas($this);                    
				$ret = array_merge($ret, $sub);
			}
			
			$this->referencedSchemaList = $ret;
		}
		
		return $this->referencedSchemaList;
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
