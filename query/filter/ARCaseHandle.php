<?php

/**
 *	Abstracts SQLs CASE statement
 *	http://dev.mysql.com/doc/refman/5.0/en/control-flow-functions.html
 *
 *  @package activerecord.query.filter
 *  @author Integry Systems
 */
class ARCaseHandle implements ARFieldHandleInterface
{
  	protected $conditions = array();

  	protected $defaultValue;

	public function __construct(ARFieldHandleInterface $defaultValue = null)
  	{
		if (!is_null($defaultValue))
		{
			$this->defaultValue = $defaultValue;
		}
	}

	public function addCondition(Condition $condition, ARFieldHandleInterface $value)
	{
		$this->conditions[] = array($condition, $value);
	}

	public function prepareValue($value)
	{
		return $value;
	}

	public function escapeValue($value)
	{
		return $value;
	}

	public function toString()
	{
	  	$cases = array('CASE');

		foreach ($this->conditions as $cond)
	  	{
			$cases[] = 'WHEN ' . $cond[0]->createChain() . ' THEN ' . $cond[1]->toString();
		}

		if ($this->defaultValue)
		{
			$cases[] = 'ELSE ' . $this->defaultValue->toString();
		}

		$cases[] = 'END';

		return implode(' ', $cases);
	}
}

?>