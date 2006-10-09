<?php

require_once("filter/ARFilter.php");

/**
 *
 * @package activerecord.query.filter
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
				$statementList[] = $fieldName." = '".$newValue."'";
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
	 */
	public function addModifier($fieldName, $fieldValue)
	{
		$this->modifierList[$fieldName] = $fieldValue;
	}
}

?>
