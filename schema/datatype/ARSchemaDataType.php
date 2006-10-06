<?php

/**
 * Abstract schema data type representing type of a table column
 * 
 * @package activerecord.schema.datatype
 */
abstract class ARSchemaDataType {
	
	/**
	 * Data type length (usually in bytes)
	 *
	 * @var int
	 */
	protected $length;
	
	protected static $instanceMap = array();
	
	protected function __construct($typeLength) {
		$this->length = $typeLength;
	}
	
	/**
	 * Gets an instance of schema data type onject
	 * 
	 * As there can be only one instance of concrete datatype with concrete length, 
	 * there is  no need to create a separate object every time. By folowing this rule, 
	 * object instance is created by its type and length only once and stored in an 
	 * identity map for posible future use
	 *
	 * @param string $typeClassName
	 * @param int $typeLength
	 * @return ARSchemaDataType
	 */
	public static function instance($typeClassName, $typeLength) {
		if(empty(self::$instanceMap[$typeClassName][$typeLength])) {
			$instance =  new $typeClassName($typeLength);
			self::$instanceMap[$typeClassName][$typeLength] = $instance;
		}
		return self::$instanceMap[$typeClassName][$typeLength];
	}
	
	/**
	 * Gets a length of a data type
	 *
	 * @return int
	 */
	public function getLength() {
		return $this->length;
	}
}

/**
 * Numeric data type class
 * 
 * @package activerecord.schema.datatype
 */
abstract class Numeric extends ARSchemaDataType  {
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Integer extends Numeric {
	public static function instance($typeLength = 4) {
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Float extends Numeric {
	public static function instance($typeLength = 8) {
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Bool extends Numeric {
	public static function instance() {
		return parent::instance(__CLASS__, 1);
	}
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
abstract class Literal extends ARSchemaDataType {
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Char extends Literal {
	public static function instance($typeLength = 40) {
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * Schema data type for representing variable length literal columns
 *
 * @package activerecord.schema.datatype
 */
class Varchar extends Literal {
	public static function instance($typeLength = 40) {
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Binary extends ARSchemaDataType {
	public static function instance($typeLength) {
		return parent::instance(__CLASS__, $typeLength);
	}
}


/**
 * Abstract class for data type which represents time period (date, time, hours or everything)
 *
 */
abstract class Period extends ARSchemaDataType {
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Date extends Period {
	public static function instance() {
		return parent::instance(__CLASS__, 0);
	}
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class Time extends Period {
	public static function instance() {
		return parent::instance(__CLASS__, 0);
	}
}

/**
 * ...
 * 
 * @package activerecord.schema.datatype
 */
class DateTime extends Period {
	public static function instance() {
		return parent::instance(__CLASS__, 0);
	}
}

?>