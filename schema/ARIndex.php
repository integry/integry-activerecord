<?php

/**
 * Class representing table index
 *
 * Index is a part of table schema
 *
 * @package activerecord.schema
 */
class ARIndex implements IteratorAggregate
{
	private $fieldList = array();

	/**
	 * Add a schema field to index
	 *
	 * @param ARField $field
	 */
	public function addField(ARField $field)
	{
		$this->fieldList[] = $field;
	}

	/**
	 * Required definition of interface IteratorAggregate
	 *
	 * @return Iterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->fieldList);
	}
}

?>
