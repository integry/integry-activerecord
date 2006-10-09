<?php

require_once("schema/ARSchemaException.php");

/**
 * Exception for signaling that a requested schema is not found
 *
 * @package activerecord.schema
 */
class ARSchemaNotFoundException extends ARSchemaException
{
	public function __construct($className)
	{
		parent::__construct("Schema (or class) not found (".$className.")");
	}
}

?>
