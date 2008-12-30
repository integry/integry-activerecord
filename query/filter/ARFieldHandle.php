<?php

include_once(dirname(__file__) . '/ARFieldHandleInterface.php');

/**
 * Table field access handle
 *
 * @package activerecord.query.filter
 * @author Integry Systems
 */
class ARFieldHandle implements ARFieldHandleInterface
{
	private $className = "";
	private $tableName = "";
	private $field = null;

	public function __construct($className, $fieldName, $tableAlias = false)
	{
		$schema = ActiveRecord::getSchemaInstance($className);

		if ($schema->fieldExists($fieldName))
		{
			$this->tableName = $tableAlias ? $tableAlias : $schema->getName();
			$this->field = $schema->getField($fieldName);
		}
		else
		{
			throw new ARException("Unable to find defined schema or field: " . $className. "." . $fieldName);
		}
	}

	public function setTable($tableOrAlias)
	{
		$this->tableName = $tableOrAlias;
	}

	public function toString()
	{
		return $this->tableName . "." . $this->field->getName();
	}

	public function prepareValue($value)
	{
		$dataType = $this->field->getDataType();

		// convert timestamps
		if (is_numeric($value) && ($dataType instanceof ARPeriod))
		{
			return date("Y-m-d H:i:s", $value);
		}

		return $value;
	}

	public function escapeValue($value)
	{
		$value = $this->prepareValue($value);

		$dataType = $this->field->getDataType();

		// "escape" strings
		if (($dataType instanceof ARLiteral))
		{
			if (!strlen($value))
			{
				return "''";
			}

			$value = '0x' . bin2hex($value);
		}

		return $value;
	}

	public function getField()
	{
		return $this->field;
	}

	/*
	public function __destruct()
	{
		logDestruct($this);
	}
	*/
}

?>
