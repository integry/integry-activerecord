<?php

require_once("schema/ARField.php");

/**
 * Binds a value to a schema field
 * (it is just an ordinary row field value container)
 *
 * @package activerecord
 * @author Saulius Rupainis <saulius@integry.net>
 */
class ARValueMapper
{
	/**
	 * Schema field instance
	 *
	 * @var ARSchemaField
	 */
	private $field = null;

	/**
	 * Value assigned to a schema field
	 *
	 * @var mixed
	 */
	private $value = null;

	/**
	 * A mark to indicate if a record was modified
	 *
	 * @var bool
	 */
	private $isModified = false;

	private $isNull = false;

	public function __construct(ARField $field, $value = null)
	{
		$this->field = $field;
		$this->value = $value;
	}

	/**
	 * Gets a related schema field
	 *
	 * @return ARField
	 */
	public function getField()
	{
		return $this->field;
	}

	/**
	 * Assignes a value to a field
	 *
	 * @param mixed $value
	 */
	public function set($value, $markAsModified = true)
	{
		if ($this->field instanceof ARForeignKey && !($value instanceof ActiveRecord))
		{
			throw new ARException("Invalid value parameter: must be an instance of ActiveRecord");
		}
		//if ($this->field->getDataType() instanceof ARArray && !is_array($value))
		//{
		//	throw new ARException("Invalid value parameter: must be an array");
		//}
		$this->value = $value;

		if ($markAsModified)
		{
			$this->isModified = true;
		}
	}

	public function setNull()
	{
		$this->isNull = true;
		$this->isModified = true;
	}

	public function isNull()
	{
		return $this->isNull;
	}

	/**
	 * Gets a field value
	 *
	 * @return mixed
	 */
	public function get()
	{
		return $this->value;
	}

	/**
	 * Returns true if field is being modified
	 *
	 * @return bool
	 */
	public function isModified()
	{
		return $this->isModified;
	}

	/**
	 * Marks the field as not modified (after saving, etc.)
	 */
	public function resetModifiedStatus()
	{
		$this->isModified = false;
	}

	/**
	 * Checks if this instance has an assigned value
	 *
	 * @return bool
	 */
	public function hasValue()
	{
		if ($this->value != null)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

?>