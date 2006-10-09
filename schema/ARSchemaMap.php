<?php

/**
 *
 * @package activerecord.schema
 */
class ARSchemaMap
{
	private $map = array();

	/**
	 * Creates or gets an already created Schema object for a given class $className
	 *
	 * @param string $className
	 * @return Schema
	 */
	public function getSchemaInstance($className)
	{
		if (empty($this->map[$className]))
		{
			$this->map[$className] = new ARSchema();

			/* Using PHP5 reflection api to call a static method of $className class */
			$staticDefMethod = new ReflectionMethod($className, 'defineSchema');
			$staticDefMethod->invoke(null);
			/* end block */

			//if (!$this->map[$className]->isValid()) {
			//	throw new ARException("Invalid schema (" .$className. ") definition! Make sure it has a name assigned and fields defined (record structure)");
			//}
		}
		return $this->map[$className];
	}
}

?>
