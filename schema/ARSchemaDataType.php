<?php

/**
 * Abstract schema data type representing type of a table column
 *
 * @package activerecord.schema.datatype
 * @author Integry Systems
 */
abstract class ARSchemaDataType
{
	/**
	 * Data type length (usually in bytes)
	 *
	 * @var int
	 */
	protected $length;
	protected static $instanceMap = array();

	protected function __construct($typeLength)
	{
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
	public static function instance($typeClassName, $typeLength)
	{
		if (empty(self::$instanceMap[$typeClassName][$typeLength]))
		{
			$instance = new $typeClassName($typeLength);
			self::$instanceMap[$typeClassName][$typeLength] = $instance;
		}
		return self::$instanceMap[$typeClassName][$typeLength];
	}

	/**
	 * Gets a length of a data type
	 *
	 * @return int
	 */
	public function getLength()
	{
		return $this->length;
	}

	public function getValidatedValue($value)
	{
		return $value;
	}
}

/**
 * Numeric data type class
 *
 * @package activerecord.schema.datatype
 */
abstract class ARNumeric extends ARSchemaDataType
{
	public function getValidatedValue($value)
	{
		if ($value instanceof ActiveRecord)
		{
			$value = $value->getID();
		}

		$value = (float)$value;

		return $value;
	}
}

/**
 * Array data type
 *
 * As many DBMS vendors does not support array data types, array data should be saved
 * in a serialized form (string)
 *
 * @package activerecord.schema.datatype
 */
class ARArray extends ARLiteral
{
	public static function instance()
	{
		return parent::instance(__CLASS__, 2048);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARInteger extends ARNumeric
{
	public static function instance($typeLength = 4)
	{
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARFloat extends ARNumeric
{
	public static function instance($typeLength = 8)
	{
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARBool extends ARNumeric
{
	public static function instance()
	{
		return parent::instance(__CLASS__, 1);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
abstract class ARLiteral extends ARSchemaDataType{}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARChar extends ARLiteral
{
	public static function instance($typeLength = 40)
	{
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * Schema data type for representing variable length literal columns
 *
 * @package activerecord.schema.datatype
 */
class ARVarchar extends ARLiteral
{
	public static function instance($typeLength = 40)
	{
		return parent::instance(__CLASS__, $typeLength);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARText extends ARLiteral
{
	public static function instance()
	{
		return parent::instance(__CLASS__, 0);
	}
}


/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARBinary extends ARSchemaDataType
{
	public static function instance($typeLength)
	{
		return parent::instance(__CLASS__, $typeLength);
	}
}


/**
 * Abstract class for data type which represents time period (date, time, hours or everything)
 *
 */
abstract class ARPeriod extends ARSchemaDataType
{
	public function getValidatedValue($value)
	{
		if (is_numeric($value))
		{
			$value = ARSerializableDateTime::createFromTimeStamp($value);
		}

		return $value;
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARDate extends ARPeriod
{
	public static function instance()
	{
		return parent::instance(__CLASS__, 0);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARTime extends ARPeriod
{
	public static function instance()
	{
		return parent::instance(__CLASS__, 0);
	}
}

/**
 * ...
 *
 * @package activerecord.schema.datatype
 */
class ARDateTime extends ARPeriod
{
	public static function instance()
	{
		return parent::instance(__CLASS__, 0);
	}
}

?>