<?php

/**
 * Abstract condition element used in WHERE clause
 *
 * It allows programmer to create schema independent conditional statems and use it
 * with a filter (ARFilter subclass) that is applied to a record set (deletion,
 * select or etc.)
 *
 * @package activerecord.query.filter
 * @author Integry Systems
 */
abstract class Condition
{

	/**
	 * Operator string
	 *
	 * @var unknown_type
	 */
	protected $operatorString = "";
	protected $ORCondList = array();
	protected $ANDCondList = array();

	/**
	 * Abstract class for representing condition as a string
	 *
	 */
	abstract public function toString();

	public function createChain()
	{
		$condStr = "(" . $this->toString();
		foreach($this->ANDCondList as $andCond)
		{
			$condStr .= " AND " . $andCond->createChain();
		}
		foreach($this->ORCondList as $orCond)
		{
			$condStr .= " OR " . $orCond->createChain();
		}
		$condStr .= ")";
		return $condStr;
	}

	public function setOperatorString($str)
	{
		$this->operatorString = $str;
	}

	public function getOperatorString($str)
	{
		return $this->operatorString;
	}

	/**
	 * Appends a condition with a strict (AND) requirement
	 *
	 * @param Condition $cond
	 */
	public function addAND(Condition $cond)
	{
		$this->ANDCondList[] = $cond;
	}

	/**
	 *
	 *
	 * @param Condition $cond
	 */
	public function addOR(Condition $cond)
	{
		$this->ORCondList[] = $cond;
	}

	/**
	 * Creates an expression handle, which can be used in query field list for advanced calculations
	 *
	 * For example:
	 * <code>
	 *		$cond = new EqualsOrMoreCond(new ARFieldHandle('ProductPrice', 'price'), 20);
	 *		$filter->addField('SUM(' . $cond->getExpressionHandle() . ')');
	 * </code>
	 *
	 * @param array $array Array of Condition object instances
	 * @return Condition
	 */
	public function getExpressionHandle()
	{
		return new ARExpressionHandle($this->createChain());
	}

	public function removeCondition(BinaryCondition $condition)
	{
		$str = $condition->toString();

		if ($this->toString() == $str)
		{
			$this->leftSide = new ARExpressionHandle(1);
			$this->rightSide = 1;
			$this->operatorString = '=';
		}

		foreach (($this->ANDCondList + $this->ORCondList) as $cond)
		{
			if ($cond instanceof BinaryCondition)
			{
				$cond->removeCondition($condition);
			}
		}
	}

	/**
	 * Merges an array of conditions into one condition
	 *
	 * @param array $array Array of Condition object instances
	 * @return Condition
	 */
	public static function mergeFromArray($array)
	{
		$baseCond = array_shift($array);
		foreach ($array as $cond)
		{
			$baseCond->addAND($cond);
		}

		return $baseCond;
	}

	public function __destruct()
	{
//		logDestruct($this, $this->createChain());
	}
}

/**
 * Base class for unary conditions (using unary operators, such as NULL)
 *
 * @package activerecord.query.filter
 */
class UnaryCondition extends Condition
{
	protected $fieldHandle = null;

	public function __construct(ARFieldHandleInterface $fieldHandle, $operatorString)
	{
		$this->fieldHandle = $fieldHandle;
		$this->operatorString = $operatorString;
	}

	public function toString()
	{
		return $this->fieldHandle->toString().' '.$this->operatorString;
	}
}

/**
 * Base class for binary conditions (using binary operators, such as =, =<, =>, > and etc.)
 *
 * @package activerecord.query.filter
 */
abstract class BinaryCondition extends Condition
{
	protected $leftSide = "";
	protected $rightSide = "";

	protected $leftSideTableName = "";
	protected $rightSideTableName = "";

	public function __construct(ARFieldHandleInterface $leftSide, $rightSide)
	{
		$this->leftSide = $leftSide;
		$this->rightSide = $rightSide;
	}

	public function toString()
	{
		$condStr = "";
		$condStr = $this->leftSide->toString().$this->operatorString;
		if ($this->rightSide instanceof ARFieldHandleInterface)
		{
			$condStr .= $this->rightSide->toString();
		}
		else
		{
			$condStr .= $this->leftSide->prepareValue($this->rightSide);
		}
		return $condStr;
	}
}

/**
 * Binary condition with operator
 * <code>
 * new OperatorCond("User.creatimDate", "2005-02-02", ">");
 * </code>
 *
 * @package activerecord.query.filter
 */
class OperatorCond extends BinaryCondition
{
	protected $operatorString;

	public function __construct($leftSide, $rightSide, $operator)
	{
		parent::__construct($leftSide, $rightSide);
		$this->operatorString = $operator;
	}
}

/**
 * Equals condition
 *
 * @package activerecord.query.filter
 */
class EqualsCond extends BinaryCondition
{
	protected $operatorString = "=";
}

/**
 * Not equals condition
 *
 * @package activerecord.query.filter
 */
class NotEqualsCond extends BinaryCondition
{
	protected $operatorString = "!=";
}

/**
 * Is NULL condition
 *
 * @package activerecord.query.filter
 */
class IsNullCond extends UnaryCondition
{
	public function __construct(ARFieldHandleInterface $fieldHandle)
	{
		parent::__construct($fieldHandle, "IS NULL");
	}
}
/**
 * IS NOT NULL condition
 *
 * @package activerecord.query.filter
 */
class IsNotNullCond extends UnaryCondition
{
	public function __construct(ARFieldHandleInterface $fieldHandle)
	{
		parent::__construct($fieldHandle, "IS NOT NULL");
	}
}

/**
 * Less than condition
 *
 * @package activerecord.query.filter
 */
class LessThanCond extends BinaryCondition
{
	protected $operatorString = "<";
}

/**
 * Condition using ">" operator
 *
 * @package activerecord.query.filter
 */
class MoreThanCond extends BinaryCondition
{
	protected $operatorString = ">";
}

/**
 * Condition using LIKE operator
 *
 * @package activerecord.query.filter
 */
class LikeCond extends BinaryCondition
{
	protected $operatorString = " LIKE ";
}

/**
 * Condition using REGEXP operator
 *
 * @package activerecord.query.filter
 */
class RegexpCond extends BinaryCondition
{
	protected $operatorString = " REGEXP ";
}

/**
 * Condition using IS operator
 *
 * @package activerecord.query.filter
 */
class ISCond extends BinaryCondition
{
	protected $operatorString = " IS ";
}

/**
 * Condition using IN operator
 *
 * @package activerecord.query.filter
 */
class INCond extends BinaryCondition
{
	protected $operatorString = " IN ";

	public function __construct(ARFieldHandleInterface $leftSide, $rightSide)
	{
		if (is_array($rightSide))
		{
		  	$rightSide = implode(', ', array_filter($rightSide, array($this, 'filterEmptyValues')));
		}

		if (!$rightSide)
		{
			$rightSide = 0;
		}

		parent::__construct($leftSide, "(".$rightSide.")");
	}

	private function filterEmptyValues($value)
	{
		return is_numeric($value) || trim($value);
	}
}

/**
 * Condition using NOT IN operator
 *
 * @package activerecord.query.filter
 */
class NotINCond extends INCond
{
	protected $operatorString = " NOT IN ";
}

/**
 * Condition using "=>" operator
 *
 * @package activerecord.query.filter
 */
class EqualsOrMoreCond extends BinaryCondition
{
	protected $operatorString = ">=";
}

/**
 * Condition using "=<" operator
 *
 * @package activerecord.query.filter
 */
class EqualsOrLessCond extends BinaryCondition
{
	protected $operatorString = "<=";
}

class AndChainCondition extends Condition
{
	public function __construct($array = array())
	{
		foreach ($array as $cond)
		{
			$this->addAND($cond);
		}
	}

	public function toString()
	{

	}

	public function createChain()
	{
		$conds = array();
		foreach ($this->ANDCondList as $cond)
		{
			$conds[] = '(' . $cond->createChain() . ')';
		}

		return '(' . implode(' AND ', $conds) . ')';
	}
}

?>