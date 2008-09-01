<?php

/**
 * Schema field
 *
 * @package activerecord.schema
 * @author Integry Systems
 */
class ARField
{
	protected $name = "";
	protected $dataType = null;
	protected $typeLength = null;

	/**
	 * Schema field (representing table column) constructor
	 *
	 * @param string $fieldName
	 * @param ARSchemaDataType $dataType
	 */
	public function __construct($fieldName, ARSchemaDataType $dataType)
	{
		$this->name = $fieldName;
		$this->dataType = $dataType;
	}

	/**
	 * Gets a name of this field
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Gets a field data type
	 *
	 * @return ARSchemaDataType
	 */
	public function getDataType()
	{
		return $this->dataType;
	}
}

/**
 * Foreign key interface
 *
 * @package activerecord.schema
 *
 */
interface ARForeignKey
{
}

/**
 * Foreign key field class representing relations between tables
 *
 * @package activerecord.schema
 */
class ARForeignKeyField extends ARField implements ARForeignKey
{
	protected $foreignTableName;
	protected $foreignClassName;
	protected $foreignFieldName;
	protected $referenceName;
	protected $referenceFieldName;

	/**
	 * Creates ARForeignKeyField instance
	 *
	 * @param string $fieldName
	 * @param string $foreignTableName
	 * @param string $foreignClassName
	 * @param int $dataType
	 * @param int $typeLength
	 */
	public function __construct($fieldName, $foreignTableName, $foreignFieldName, $foreignClassName = null, ARSchemaDataType $dataType)
	{
		parent::__construct($fieldName, $dataType);
		$this->foreignClassName = !empty($foreignClassName) ? $foreignClassName : $foreignTableName;
		$this->foreignTableName = $foreignTableName;
		$this->foreignFieldName = $foreignFieldName;

		$this->referenceFieldName = $this->referenceName = ucfirst(substr($this->name, 0, -2));

		if (!$this->referenceName)
		{
			$this->referenceName = $this->foreignClassName;
		}

		if (!$this->referenceFieldName)
		{
			$this->referenceFieldName = $this->name;
		}

		if ($this->foreignClassName != $this->referenceName)
		{
			$this->referenceName = $this->foreignClassName . '_' . $this->referenceName;
		}
	}

	/**
	 * Gets a class name (subclass of ActiveRecord) of a foreign table.
	 *
	 * If no class name were set by __construct it is considered that a class is same
	 * as a table name
	 *
	 * @return string
	 */
	public function getForeignClassName()
	{
		return $this->foreignClassName;
	}

	/**
	 * Gets a foreign table name (involved in relationship)
	 *
	 * @return string
	 */
	public function getForeignTableName()
	{
		return $this->foreignTableName;
	}

	/**
	 * Gets a field of foreign table that is using in relationship definition
	 *
	 * @return string
	 */
	public function getForeignFieldName()
	{
		return $this->foreignFieldName;
	}

	public function getReferenceName()
	{
		return $this->referenceName;
	}

	public function getReferenceFieldName()
	{
		return $this->referenceFieldName;
	}
}

/**
 * Primary-foreign key field (PF) class that is mainly used in many-to-many relationships
 *
 * @package activerecord.schema
 */
class ARPrimaryForeignKeyField extends ARForeignKeyField implements ARPrimaryKey
{
}

/**
 * Primary key interface
 *
 * @package activerecord.schema
 *
 */
interface ARPrimaryKey
{
}

/**
 * Primary key field class.
 *
 * @package activerecord.schema
 */
class ARPrimaryKeyField extends ARField implements ARPrimaryKey
{
}

?>