<?php

require_once("filter/ARFilter.php");

/**
 *
 * @package activerecord.query.filter
 * @author Integry Systems
 */
class ARUpdateFilter extends ARFilter
{
	/**
	 * List of modifiers
	 *
	 * @var unknown_type
	 */
	private $modifierList = array();

	public function createString()
	{
		$result = "";
		if (!empty($this->modifierList))
		{
			$result .= " SET ";
			$statementList = array();
			foreach($this->modifierList as $fieldName => $newValue)
			{
				if ($newValue instanceof ARExpressionHandle)
				{
					$value = $newValue->toString();
				}
				else
				{
					$value = "'".$newValue."'";
				}
				$statementList[] = $fieldName . " = ". $value;
			}
			$result .= implode(", ", $statementList);
		}
		$result .= parent::createString();
		return $result;
	}

	/**
	 * Sets a field modifier (value which will be assigned for a row(s) matching
	 * update condition)
	 *
	 * @param string $fieldName
	 * @param string $fieldValue
	 *
	 * @todo add ARFieldHandle (field object) instead of $fieldName (string)
	 */
	public function addModifier($fieldName, $fieldValue)
	{
		$this->modifierList[$fieldName] = $fieldValue;
	}

	public function isModifierSet()
	{
		return count($this->modifierList) > 0;
	}
}

?>
