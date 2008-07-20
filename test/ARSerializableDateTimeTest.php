<?php

require_once dirname(__FILE__) . '/Initialize.php';

ClassLoader::import("library.activerecord.ARSerializableDateTime");

/**
 * @author Integry Systems
 * @package test.activerecord
 */
class ARSerializableDateTimeTest extends UnitTest
{
	public function getUsedSchemas()
	{
		return array();
	}

	public function testSerializeDate()
	{
		$myBirthday = new ARSerializableDateTime('1985-01-30');

		$myBirthdaySerialized = serialize($myBirthday);
		$myBirthday = unserialize($myBirthdaySerialized);

		$this->assertEqual($myBirthday->format('Y-m-d'), '1985-01-30');
	}

	public function testSerializeCurrentDate()
	{
		$currentDate = new ARSerializableDateTime();

		$currentDateSerialized = serialize($currentDate);
		$currentDate = unserialize($currentDateSerialized);

		$this->assertEqual($currentDate->format('Y-m-d'), date('Y-m-d'));
	}

	public function testSerializeNullDate()
	{
		$nullDate = new ARSerializableDateTime(null);

		$nullDateSerialized = serialize($nullDate);
		$nullDate = unserialize($nullDateSerialized);

		$this->assertEqual($nullDate->format('Y-m-d'), null);
		$this->assertTrue($nullDate->isNull());
	}
}
?>