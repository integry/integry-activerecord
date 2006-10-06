<?php

/**
 * Abstract condition element used in WHERE clause
 * 
 * It allows programmer to create schema independent conditional statems and use it 
 * with a filter (ARFilter subclass) that is applied to a record set (deletion, 
 * select or etc.)
 *
 * @package activerecord.query.filter
 */
abstract class Condition {
	
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
	
	public function createChain() {
		
		$condStr = "(" . $this->toString();
		foreach ($this->ANDCondList as $andCond) {
			$condStr .= " AND " . $andCond->createChain();
		}
		foreach ($this->ORCondList as $orCond) {
			$condStr .= " OR " . $orCond->createChain();
		}
		$condStr .= ")";
		return $condStr;
	}
	
	public function setOperatorString($str) {
		$this->operatorString = $str;
	}
	
	public function getOperatorString($str) {
		return $this->operatorString;
	}
	
	/**
	 * Appends a condition with a strict (AND) requirement
	 *
	 * @param Condition $cond
	 */
	public function addAND(Condition $cond) {
		$this->ANDCondList[] = $cond;
	}
	
	/**
	 * 
	 *
	 * @param Condition $cond
	 */
	public function addOR(Condition $cond) {
		$this->ORCondList[] = $cond;
	}
}

/**
 * Base class for unary conditions (using unary operators, such as NULL)
 *
 * @package activerecord.query.filter
 */
class UnaryCondition  extends Condition {
	
	protected $fieldHandle = null;

	public function __construct(ARFieldHandle $fieldHandle, $operatorString) {
		$this->fieldHandle = $fieldHandle;
		$this->operatorString = $operatorString;
	}

	public function toString() {
		return $this->fieldHandle->toString() . ' ' . $this->operatorString;
	}
}

/**
 * Base class for binary conditions (using binary operators, such as =, =<, =>, > and etc.)
 *
 * @package activerecord.query.filter
 */
abstract class BinaryCondition extends Condition {
	
	protected $leftSide = "";
	protected $rightSide = "";
	
	protected $leftSideTableName = "";
	protected $rightSideTableName = "";
	
	
	public function __construct(ARFieldHandle $leftSide, $rightSide) {
		
		$this->leftSide = $leftSide;
		$this->rightSide = $rightSide;
	}
	
	public function toString() {

		$condStr = "";
		$condStr = $this->leftSide->toString() . $this->operatorString;
		if ($this->rightSide instanceof ARFieldHandle) {
			$condStr .= $this->rightSide->toString();
		} else {
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
class OperatorCond extends BinaryCondition {
  
  	protected $operatorString;
  	
  	public function __construct($leftSide, $rightSide, $operator) {
	    
	    parent::__construct($leftSide, $rightSide);
	    $this->operatorString = $operator;
	}
}

/**
 * Equals condition
 *
 * @package activerecord.query.filter
 */
class EqualsCond extends BinaryCondition {
	protected $operatorString = "=";
}

/**
 * Less than condition
 *
 * @package activerecord.query.filter
 */
class LessThanCond extends BinaryCondition {
	protected $operatorString = "<";
}

/**
 * Condition using ">" operator
 *
 * @package activerecord.query.filter
 */
class MoreThanCond extends BinaryCondition {
	protected $operatorString = ">";
}

/**
 * Condition using LIKE operator
 *
 * @package activerecord.query.filter
 */
class LikeCond extends BinaryCondition {
  
  	protected $operatorString = " LIKE ";
	
	public function __construct(ARFieldHandle $leftSide, $rightSide) {
		parent::__construct($leftSide, "%" . $rightSide . "%");
	}
}

/**
 * Condition using IN operator
 *
 * @package activerecord.query.filter
 */
class INCond extends BinaryCondition {
	protected $operatorString = " IN ";
	
	public function __construct(ARFieldHandle $leftSide, $rightSide) {
		parent::__construct($leftSide, "(" . $rightSide . ")");
	}
}

/**
 * Condition using "=>" operator
 *
 * @package activerecord.query.filter
 */
class EqualsOrMoreCond extends BinaryCondition {
	protected $operatorString = ">=";
}


/**
 * Condition using "=<" operator
 *
 * @package activerecord.query.filter
 */
class EqualsOrLessCond extends BinaryCondition {
	protected $operatorString = "<=";
}

?>