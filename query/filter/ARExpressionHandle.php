<?php

class ARExpressionHandle implements ARFieldHandleInterface
{
  	protected $expression;
  	
	public function __construct($expression)
  	{
	    $this->expression = $expression;
	}
	
	public function prepareValue($value)
	{
		return '"' . $value . '"';
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