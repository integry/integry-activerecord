<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "C:/wamp/www/activerecord");
require_once("util/ARSQLGenerator.php");

include_once('c:\wamp\www\simpletest\unit_tester.php');
include_once('c:\wamp\www\simpletest\reporter.php');

include_once('models.php');

class Lentele extends ActiveRecord {
	
	public static function defineSchema($className = __CLASS__) {
		
		$schema = self::getSchemaInstance($className);
		$schema->setName("Test");
		$schema->registerField(new ARPrimaryKeyField("ID", Integer::instance()));
		//$schema->registerField(new ARPrimaryKeyField("ID2", Integer::instance()));
		$schema->registerField(new ARField("Integer_tipas", Integer::instance(5)));			
		$schema->registerField(new ARField("Integer_tipas_empty", Integer::instance()));		
		$schema->registerField(new ARField("Varchar_tipas_empty", Varchar::instance()));
		$schema->registerField(new ARField("Char_tipas_empty", Char::instance()));	
		$schema->registerField(new ARField("Char_tipas", Char::instance(20)));	
		$schema->registerField(new ARField("Varchar_tipas", Varchar::instance(20)));
		$schema->registerField(new ARField("Text_tipas", Varchar::instance(255)));
		$schema->registerField(new ARField("DateTime_tipas", ARDateTime::instance()));
		$schema->registerField(new ARField("Time_tipas", Time::instance()));
		$schema->registerField(new ARField("Date_tipas", Date::instance()));
		$schema->registerField(new ARField("Bool_tipas", Bool::instance()));
		$schema->registerField(new ARField("Float_tipas", Float::instance(10)));
		$schema->registerField(new ARField("Binary_tipas", Binary::instance(10)));
		
		$schema->registerField(new ARField("Char_to_int", Char::instance(10)));
	}	
}

class LenteleModified extends Lentele {
	
	public static function defineSchema($className = __CLASS__) {
		
		parent::defineSchema("LenteleModified");
		$schema = self::getSchemaInstance($className);
		
		$schema->registerField(new ARField("Integer_new", Integer::instance(5)));	
		$schema->registerField(new ARField("Char_new", Char::instance(20)));	
		//$schema->registerField(new ARForeignKeyField("postID", "BlogPost", "ID", null, Integer::instance()));
		//$schema->registerField(new ARForeignKeyField("postcommentID", "BlogComment", "ID", null, Integer::instance()));
	}	
}

/**
* Char_new shoud be droped
*/
class LenteleDrop extends Lentele {
	
	public static function defineSchema($className = __CLASS__) {
		
		parent::defineSchema("LenteleDrop");
		$schema = self::getSchemaInstance($className);
		
		$schema->registerField(new ARField("Integer_new", Integer::instance(5)));	
		
		//$schema->registerField(new ARField("Char_new", Char::instance(20)));	
		//$schema->registerField(new ARForeignKeyField("postID", "BlogPost", "ID", null, Integer::instance()));
		//$schema->registerField(new ARForeignKeyField("postcommentID", "BlogComment", "ID", null, Integer::instance()));
	}	
}

class LenteleChange extends Lentele {
	
	public static function defineSchema($className = __CLASS__) {
		
		parent::defineSchema("LenteleChange");
		$schema = self::getSchemaInstance($className);
		
		$schema->registerField(new ARField("Integer_new", Integer::instance(10)));
		$schema->registerField(new ARField("Char_to_int", Integer::instance(10)));
	}	
}


class TestGenerate extends UnitTestCase {
  
  	public $db;  	
  	//public $type = "mysql";
  	public $type = "pgsql";
  	//public $type = "";
  	
  	public $gen;
  	
  	function TestGenerate() {
		
		parent::__construct();				
		if ($this->type == "mysql") {
		
			$this->dsn = "mysql://root@localhost/demoproject";
		} else if ($this->type == "pgsql") {
			
			$this->dsn = "pgsql://pgsql@192.168.1.6/ezpdo";  
		}
	}
  	
	function testDelete() {
	
		ActiveRecord::setDSN($this->dsn);
		$this->db = ActiveRecord::getDBConnection();	
		$db_info = $this->db->getDataBaseInfo();
				
		$db_info->GetTables();		
		if ($db_info->hasTable("test")) {
				
			$this->db->executeUpdate("DROP TABLE Test");
		} 
		if ($db_info->hasTable("BlogComment")) {
				
			$this->db->executeUpdate("DROP TABLE BlogComment");
		}
		if ($db_info->hasTable("BlogPost")) {
				
			$this->db->executeUpdate("DROP TABLE BlogPost");
		}		
	}
	
	function testCreate() {	

		$this->gen = ARSQLGenerator::getInstance(ActiveRecord::getDbConnection());					
		//echo $this->gen->generateTableDDL("Lentele", "<br>");
		$this->db->executeUpdate($this->gen->generateTableDDL("Lentele"));
		$this->db->executeUpdate($this->gen->generateTableDDL("BlogPost"));			
		$this->db->executeUpdate($this->gen->generateTableDDL("BlogComment"));	
	}
	
	function ntestInsert() {
	  	
		$test =	ActiveRecord::getNewInstance("Lentele");	
		$test->Integer_tipas->Set(5);
		$test->Bool_tipas->Set(true);
	  	$test->save();	  	
	  	$test2 = ActiveRecord::getNewInstance("Lentele");
		$test2->Integer_tipas->Set(5);
	  	$test2->save();		

	  	$test3 = ActiveRecord::getInstanceById("Lentele", $test->getId(), true);	
	  	//echo $test3->Bool_tipas->Get();	  	
	}
	
	function testAddColumns() {
	  	  
		$this->gen = ARSQLGenerator::getInstance(ActiveRecord::getDbConnection());				
		$this->assertTrue($this->gen->generateTableModify("Lentele", true) == '');
		$this->assertTrue($this->gen->generateTableModify("BlogPost", true) == '');
		$this->assertTrue($this->gen->generateTableModify("BlogComment", true) == '');
		
		//echo $this->gen->generateTableModify("LenteleModified", true);
		$this->assertTrue($this->gen->generateTableModify("LenteleModified", true) != '');
		$this->db->executeUpdate($this->gen->generateTableModify("LenteleModified"));
	}
	
	function testDropColumns() {
		
		$this->assertTrue($this->gen->generateTableModify("LenteleModified", true) == '');  	  		
		$this->assertTrue($this->gen->generateTableModify("LenteleDrop", false) == '');  	  
		$this->assertTrue($this->gen->generateTableModify("LenteleDrop", true) != '');  	  
		//echo $this->gen->generateTableModify("LenteleDrop", true);	
		$this->db->executeUpdate($this->gen->generateTableModify("LenteleDrop", true));	
	}
	
	function testChangeColumns() {
				
		$this->db->executeUpdate($this->gen->generateTableModify("LenteleChange", true));	
	}
}

$test = &new TestGenerate();
$test->run(new HtmlReporter());






?>