<?php

/**
 *
 * @package activerecord.query.filter
 * @author Integry Systems
 */
interface ARFieldHandleInterface
{
	public function toString();
	public function prepareValue($value);
	public function escapeValue($value);
}

?>