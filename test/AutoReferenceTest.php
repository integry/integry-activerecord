<?php

require_once dirname(__FILE__) . '/Initialize.php';

ClassLoader::import('library.activerecord.ARSerializableDateTime');

/**
 * @author Integry Systems
 * @package test.activerecord
 */
class AutoReferenceTest extends UnitTest
{
	public function getUsedSchemas()
	{
		return array();
	}

	public function testAutoReference()
	{
		ActiveRecordModel::executeUpdate('DROP TABLE IF EXISTS AutoReferenceParent');
		ActiveRecordModel::executeUpdate('DROP TABLE IF EXISTS AutoReferenceChild');

		ActiveRecordModel::executeUpdate('
			CREATE TABLE AutoReferenceParent (
			ID INTEGER UNSIGNED NOT NULL,
			referenceID INTEGER UNSIGNED NOT NULL,
			name VARCHAR(60) NOT NULL,
			CONSTRAINT PK_Manufacturer PRIMARY KEY (ID))');

		ActiveRecordModel::executeUpdate('
			CREATE TABLE AutoReferenceChild (
			ID INTEGER UNSIGNED NOT NULL,
			name VARCHAR(60) NOT NULL,
			CONSTRAINT PK_Manufacturer PRIMARY KEY (ID))');

		$child =  ActiveRecordModel::getNewInstance('AutoReferenceChild');
		$child->name->set('child');
		$child->save();

		$parent = ActiveRecordModel::getNewInstance('AutoReferenceParent');
		$parent->setID(4);
		$parent->name->set('parent');
		$parent->reference->set($child);
		$parent->save();

		ActiveRecordModel::clearPool();

		// test loading data array
		$f = new ARSelectFilter(new EqualsCond(new ARFieldHandle('AutoReferenceParent', 'ID'), 4));
		$array = array_shift(ActiveRecordModel::getRecordSetArray('AutoReferenceParent', $f));
		$this->assertEqual($array['ID'], 4);
		$this->assertEqual($array['Reference']['name'], 'child');

		ActiveRecordModel::clearPool();

		// test loading instance by ID
		$newParent = ActiveRecordModel::getInstanceByID('AutoReferenceParent', 4, ActiveRecordModel::LOAD_DATA);
		$this->assertEqual($newParent->reference->get()->name->get(), 'child');
		$this->assertNotSame($parent, $newParent);
		$this->assertNotSame($child, $newParent->reference->get());

		// test loading record set
		$newParent = ActiveRecordModel::getRecordSet('AutoReferenceParent', $f)->get(0);
		$this->assertEqual($newParent->reference->get()->name->get(), 'child');
		$this->assertNotSame($parent, $newParent);
		$this->assertNotSame($child, $newParent->reference->get());

		ActiveRecordModel::executeUpdate('DROP TABLE AutoReferenceParent');
		ActiveRecordModel::executeUpdate('DROP TABLE AutoReferenceChild');
	}
}

class AutoReferenceParent extends ActiveRecord
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName($className);

		$schema->registerField(new ARPrimaryKeyField('ID', ARInteger::instance()));
		$schema->registerField(new ARField('name', ARVarchar::instance(60)));
		$schema->registerField(new ARForeignKeyField('referenceID', 'AutoReferenceChild', 'ID', null, ARInteger::instance()));
		$schema->registerAutoReference('referenceID');
	}
}

class AutoReferenceChild extends ActiveRecord
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName($className);

		$schema->registerField(new ARPrimaryKeyField('ID', ARInteger::instance()));
		$schema->registerField(new ARField('name', ARVarchar::instance(60)));
	}
}

?>