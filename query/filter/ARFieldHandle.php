<?php

include_once(dirname(__file__) . '/ARFieldHandleInterface.php');

/**
 * Table field access handle
 *
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

	public function toString()
	{
		return $this->tableName.".".$this->field->getName();
	}

	public function prepareValue($value)
	{
		$dataType = $this->field->getDataType();
		
		// convert timestamps
        if (is_numeric($value) && ($dataType instanceof ARPeriod))
		{
            return date("'Y-m-d H:i:s'", $value);
        }
		
        // "escape" strings
        if (($dataType instanceof ARLiteral) || ($dataType instanceof ARPeriod))
		{
			if (!strlen($value))
			{
				return "''";
			}
			
			return '0x' . bin2hex($value);
		}
		
        else
		{
			return $value;
		}
	}
}

?>
