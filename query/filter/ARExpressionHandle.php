<?php

include_once(dirname(__file__) . '/ARFieldHandleInterface.php');

/**
 * 
 * @package activerecord.query.filter
 * @author Integry Systems
 */
class ARExpressionHandle implements ARFieldHandleInterface
{
  	protected $expression;
  	
	public function __construct($expression)
  	{
	    $this->expression = $expression;
	}
	
	public function prepareValue($value)
	{
		return is_numeric($value) ? $value : '0x' . bin2hex($value);
	}	
	
	public function toString()
	{
	  	return $this->expression;
	}	
	
	public function __toString()
	{
	  	return $this->toString();
	}	
}

?>