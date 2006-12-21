<?php

/**
 * Table field access handle
 *
 */
class ARFieldHandle
{
	private $className = "";
	private $tableName = "";
	private $field = null;

	public function __construct($className, $fieldName)
	{
		$schema = ActiveRecord::getSchemaInstance($className);

		if ($schema->fieldExists($fieldName))
		{
			$this->tableName = $schema->getName();
			$this->field = $schema->getField($fieldName);
		}
		else
		{
			throw new ARException("Unable to find defined schema or field: " . $className. "." . $fieldName);
		}
	}

	public function toString()
	{
		return $this->tableName.".".$this->field->getName();
	}

	public function prepareValue($value)
	{
		$dataType = $this->field->getDataType();
		if (($dataType instanceof ARLiteral) || ($dataType instanceof ARPeriod))
		{
			return "'".$value."'";
		}
		else
		{
			return $value;
		}
	}
}

?>
