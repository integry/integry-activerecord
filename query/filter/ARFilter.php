<?php

require_once("filter/Condition.php");

/**
 * Abstract ActiveRecord filter
 *
 * @package activerecord.query.filter
 */
abstract class ARFilter
{
	/**
	 * WHERE condition container
	 *
	 * @var Condition
	 */
	protected $condition = null;

	/**
	 * Creates a textual filter representation
	 *
	 * This string might be used as a part of SQL query
	 *
	 */
	public function createString()
	{
		if ($this->condition != null)
		{
			return " WHERE " . $this->condition->createChain();
		}
		else
		{
			return "";
		}
	}

	/**
	 * Adds a constraint which will be used in SQL query
	 *
	 * @param string $condition
	 */
	public function setCondition(Condition $condition)
	{
		$this->condition = $condition;
	}

	public function getCondition()
	{
		return $this->condition;
	}

	public function isConditionSet()
	{
        return ($this->condition instanceof Condition);
	}

	public function mergeCondition(Condition $cond)
	{
		if ($this->condition != null)
		{
			$this->condition->addAND($cond);
		}
		else
		{
			$this->condition = $cond;
		}
	}

	public function __toString()
	{
		return $this->createString()."\n<br/>";
	}

}

?>
