<?php

/**
 * Table field access handle
 *
 */
class ARFieldHandle {

	private $className = "";
	private $tableName = "";
	private $field = null;
	
	public function __construct($className, $fieldName) {
		$schema = ActiveRecord::getSchemaInstance($className);
		
		if ($schema->fieldExists($fieldName)) {
			$this->tableName = $schema->getName();
			$this->field = $schema->getField($fieldName);
		}
	}
	
	public function toString() {
		return $this->tableName . "." . $this->field->getName();
	}
	
	public function prepareValue($value) {
		$dataType = $this->field->getDataType();
		if ($dataType instanceof Literal || $dataType instanceof Period) {
			return "'" . $value . "'";
		} else {
			return $value;
		}
	}
}

?>