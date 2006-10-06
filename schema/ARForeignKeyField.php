<?php

require_once("schema/ARForeignKey.php");
require_once("schema/ARField.php");

/**
 * Foreign key field class representing relations between tables
 * 
 * @package activerecord.schema
 */
class ARForeignKeyField extends ARField implements ARForeignKey {

	protected $foreignTableName = "";
	protected $foreignClassName = "";
	protected $foreignFieldName = "";
	
	/**
	 * Creates ARForeignKeyField instance
	 *
	 * @param string $fieldName
	 * @param string $foreignTableName
	 * @param string $foreignClassName
	 * @param int $dataType
	 * @param int $typeLength
	 */
	public function __construct($fieldName, $foreignTableName, $foreignFieldName, $foreignClassName = null, ARSchemaDataType $dataType) {
		
		parent::__construct($fieldName, $dataType);
		$this->foreignClassName = $foreignClassName;
		$this->foreignTableName = $foreignTableName;
		$this->foreignFieldName = $foreignFieldName;
	}
	
	/**
	 * Gets a class name (subclass of ActiveRecord) of a foreign table.
	 * 
	 * If no class name were set by __construct it is considered that a class is same 
	 * as a table name
	 *
	 * @return string
	 */
	public function getForeignClassName() {
		if (!empty($this->foreignClassName)) {
			return $this->foreignClassName;
		} else {
			return $this->foreignTableName;
		}
	}
	
	/**
	 * Gets a foreign table name (involved in relationship)
	 *
	 * @return string
	 */
	public function getForeignTableName() {
		return $this->foreignTableName;
	}
	
	/**
	 * Gets a field of foreign table that is using in relationship definition
	 *
	 * @return string
	 */
	public function getForeignFieldName() {
		return $this->foreignFieldName;
	}
}

?>