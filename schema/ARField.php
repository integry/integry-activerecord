<?php

/**
 * Schema field
 *
 * @package activerecord.schema
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

?>