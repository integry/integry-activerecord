<?php

require_once("ARException.php");

/**
 * Exception for signaling that a requested record (persisted object) was not found
 *
 * @package activerecord
 */
class ARNotFoundException extends ARException {
	
	/**
	 * Type (class name) of requested record
	 *
	 * @var string
	 */
	private $className = "";
	private $recordID = "";
	
	public function __construct($className, $recordID) {
		parent::__construct("Instance of $className (ID: $recordID) not found!");
	}
}

?>