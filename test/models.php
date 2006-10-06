<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "C:/projects/K-Shop/library/activerecord");
require_once("ActiveRecord.php");

ActiveRecord::$creolePath = "C:/projects/K-Shop/library/creole";
ActiveRecord::setDSN("mysql://root@localhost/demoproject");


class Demo extends ActiveRecord {
	
	public static function defineSchema($className = __CLASS__) {
		
		$schema = self::getSchemaInstance($className);
		$schema->setName("Demo");
		$schema->registerField(new ARPrimaryKeyField("ID", Varchar::instance(15)));
		$schema->registerField(new ARField("value", Varchar::instance(200)));
	}
	
	public static function getRecordSet(ARSelectFilter $filter, $loadReferencedRecords = false) {
		return parent::getRecordSet(__CLASS__, $filter, $loadReferencedRecords);
	}
	
	public static function getInstanceByID($recordID, $loadRecordData = false, $loadReferencedRecords = false) {
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords);
	}
}

/**
 * Blog post model
 *
 */
class BlogPost extends ActiveRecord {
	
	public static function defineSchema($className = __CLASS__) {
		
		$schema = self::getSchemaInstance($className);
		$schema->setName("BlogPost");
		$schema->registerField(new ARPrimaryKeyField("ID", Integer::instance()));
		$schema->registerField(new ARField("title", Varchar::instance(200)));
		$schema->registerField(new ARField("body", Varchar::instance(1024)));
		$schema->registerField(new ARField("createdAt", DateTime::instance()));
	}
	
	public static function getRecordSet(ARSelectFilter $filter, $loadReferencedRecords = false) {
		return parent::getRecordSet(__CLASS__, $filter, $loadReferencedRecords);
	}
	
	public static function getInstanceByID($recordID, $loadRecordData = false, $loadReferencedRecords = false) {
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords);
	}
}

/**
 * Blog comment model
 *
 */
class BlogComment extends ActiveRecord {
	
	public static function defineSchema($className = __CLASS__) {
		
		$schema = self::getSchemaInstance($className);
		$schema->setName("BlogComment");
		$schema->registerField(new ARPrimaryKeyField("ID", Integer::instance()));
		$schema->registerField(new ARForeignKeyField("postID", "BlogPost", "ID", null, Integer::instance()));
		$schema->registerField(new ARField("author", 	Varchar::instance(40)));
		$schema->registerField(new ARField("email", 	Varchar::instance(40)));
		$schema->registerField(new ARField("body", 		Varchar::instance(1024)));
		$schema->registerField(new ARField("createdAt", DateTime::instance()));
	}	
}

?>