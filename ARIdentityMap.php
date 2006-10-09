<?php

/**
 * Active record instance manager (kind of cache engine)
 *
 * This class role is to ensure that every instance of concrete record is created
 * only once. This is how data consistency is ensured.
 *
 * @package activerecord
 */
class ARIdentityMap
{
	private $map = array();

	/**
	 * Gets a record from an identity map
	 *
	 * @param string $ARclassName
	 * @param mixed $recordID
	 * @return mixed ActiveRecord instance of false if no such record exists
	 */
	public function retrieve($ARclassName, $recordID)
	{
		$hash = $this->hash($recordID);
		if ($this->map[$ARclassName][$hash])
		{
			return $this->map[$ARclassName][$hash];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Stores an active record instance to a global storage. This method applies
	 *
	 * @param ActiveRecord $record
	 */
	public function store(ActiveRecord $record)
	{
		$className = get_class($record);
		$recordID = $record->getID();

		$hash = $this->hash($recordID);
		$this->map[$className][$recordID] = $record;
	}

	/**
	 * Enter description here...
	 *
	 * @param mixed $recordID
	 * @return string
	 */
	private function hash($recordID)
	{
		if (!is_array($recordID)){
		}
		else
		{
		}
	}
}


?>
