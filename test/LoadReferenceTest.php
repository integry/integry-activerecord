<?php

require_once dirname(__FILE__) . '/Initialize.php';

ClassLoader::import('library.activerecord.ARSerializableDateTime');

/**
 * @author Integry Systems
 * @package test.activerecord
 */
class LoadReferenceTest extends UnitTest
{
	public function getUsedSchemas()
	{
		return array();
	}

	public function setUp()
	{
		ActiveRecordModel::executeUpdate('DROP TABLE IF EXISTS LoadReferenceSuper');
		ActiveRecordModel::executeUpdate('DROP TABLE IF EXISTS LoadReferenceParent');
		ActiveRecordModel::executeUpdate('DROP TABLE IF EXISTS LoadReferenceChild');

		ActiveRecordModel::executeUpdate('
			CREATE TABLE LoadReferenceSuper (
			ID INTEGER UNSIGNED NOT NULL,
			referenceID INTEGER UNSIGNED NOT NULL,
			name VARCHAR(60) NOT NULL,
			CONSTRAINT PK_Manufacturer PRIMARY KEY (ID))');

		ActiveRecordModel::executeUpdate('
			CREATE TABLE LoadReferenceParent (
			ID INTEGER UNSIGNED NOT NULL,
			referenceID INTEGER UNSIGNED NOT NULL,
			name VARCHAR(60) NOT NULL,
			CONSTRAINT PK_Manufacturer PRIMARY KEY (ID))');

		ActiveRecordModel::executeUpdate('
			CREATE TABLE LoadReferenceChild (
			ID INTEGER UNSIGNED NOT NULL,
			name VARCHAR(60) NOT NULL,
			CONSTRAINT PK_Manufacturer PRIMARY KEY (ID))');

		return parent::setUp();
	}

	public function tearDown()
	{
		ActiveRecordModel::executeUpdate('DROP TABLE LoadReferenceParent');
		ActiveRecordModel::executeUpdate('DROP TABLE LoadReferenceChild');
		ActiveRecordModel::executeUpdate('DROP TABLE LoadReferenceSuper');

		return parent::tearDown();
	}

	public function testLoadAllReferences()
	{
		$child =  ActiveRecordModel::getNewInstance('LoadReferenceChild');
		$child->name->set('child');
		$child->save();

		$parent = ActiveRecordModel::getNewInstance('LoadReferenceParent');
		$parent->setID(4);
		$parent->name->set('parent');
		$parent->reference->set($child);
		$parent->save();
		$parent->setID(4);
		$parent->reload();

		$super = ActiveRecordModel::getNewInstance('LoadReferenceSuper');
		$super->setID(1);
		$super->name->set('super');
		$super->reference->set($parent);
		$super->save();

		ActiveRecordModel::clearPool();

		$newSuper = ActiveRecordModel::getInstanceByID('LoadReferenceSuper', 1, ActiveRecordModel::LOAD_DATA, true);

		$this->assertNotSame($child, $newSuper->reference->get()->reference->get());
		$this->assertNotSame($newSuper->reference->get(), $newSuper->reference->get()->reference->get());
		$this->assertEqual('child', $newSuper->reference->get()->reference->get()->name->get());
	}

	public function testLoadAllReferencesIncludingAutoLoad()
	{
		$child =  ActiveRecordModel::getNewInstance('LoadReferenceChild');
		$child->name->set('child');
		$child->save();

		$parent = ActiveRecordModel::getNewInstance('LoadReferenceParent');
		$parent->setID(4);
		$parent->name->set('parent');
		$parent->reference->set($child);
		$parent->save();
		$parent->setID(4);
		$parent->reload();

		$super = ActiveRecordModel::getNewInstance('LoadReferenceSuper');
		$super->setID(1);
		$super->name->set('super');
		$super->reference->set($parent);
		$super->save();

		$schema = ActiveRecordModel::getSchemaInstance('LoadReferenceParent');
		$schema->registerAutoReference('referenceID');

		ActiveRecordModel::clearPool();

		$newSuper = ActiveRecordModel::getInstanceByID('LoadReferenceSuper', 1, ActiveRecordModel::LOAD_DATA, true);

		$this->assertNotSame($child, $newSuper->reference->get()->reference->get());
		$this->assertNotSame($newSuper->reference->get(), $newSuper->reference->get()->reference->get());
		$this->assertEqual('child', $newSuper->reference->get()->reference->get()->name->get());
	}
}

class LoadReferenceSuper extends ActiveRecord
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName($className);

		$schema->registerField(new ARPrimaryKeyField('ID', ARInteger::instance()));
		$schema->registerField(new ARField('name', ARVarchar::instance(60)));
		$schema->registerField(new ARForeignKeyField('referenceID', 'LoadReferenceParent', 'ID', null, ARInteger::instance()));
	}
}

class LoadReferenceParent extends ActiveRecord
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName($className);

		$schema->registerField(new ARPrimaryKeyField('ID', ARInteger::instance()));
		$schema->registerField(new ARField('name', ARVarchar::instance(60)));
		$schema->registerField(new ARForeignKeyField('referenceID', 'LoadReferenceChild', 'ID', null, ARInteger::instance()));
	}
}

class LoadReferenceChild extends ActiveRecord
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