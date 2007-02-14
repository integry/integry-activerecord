<?php

class ARExpressionHandle implements ARFieldHandleInterface
{
  	protected $expression;
  	
	public function __construct($expression)
  	{
	    $this->expression = $expression;
	}
	
	public function toString()
	{
	  	return $this->expression;
	}	
}

?>