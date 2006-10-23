<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "C:/projects/activerecord");
require_once("ActiveRecord.php");
require_once("util/tree/ARTreeNode.php");

ActiveRecord::$creolePath = "C:/projects/livecart/library/creole";
ActiveRecord::setDSN("mysql://root@localhost/test");

class Catalog extends ARTreeNode
{
	public static function defineSchema($className = __CLASS__)
	{
		$schema = self::getSchemaInstance($className);
		$schema->setName("Catalog");
		
		parent::defineSchema($className);
		$schema->registerField(new ARField("name", Varchar::instance(100)));
	}
	
	public static function getRootNode()
	{
		return parent::getRootNode(__CLASS__);
	}
	
	public static function getNewInstance(ARTreeNode $parentNode)
	{
		return parent::getNewInstance(__CLASS__, $parentNode);
	}
	
	public static function getInstanceByID($recordID, $loadRecordData, $loadReferencedRecords, $loadChildRecords)
	{
		return parent::getInstanceByID(__CLASS__, $recordID, $loadRecordData, $loadReferencedRecords, $loadChildRecords);
	}
	
	public static function deleteByID($recordID)
	{
		return parent::deleteByID(__CLASS__, $recordID);
	}
}


//$electronicsCatalog = Catalog::getInstanceByID(2, true, false, true);
//print_r($electronicsCatalog->toArray());

//$monitorsCatalog = Catalog::getNewInstance($electronicsCatalog);
//$monitorsCatalog->name->set("Monitors");
//$monitorsCatalog->save();
//Catalog::deleteByID(3);

$monitorsCatalog = Catalog::getInstanceByID(9, true);
print_r($monitorsCatalog->getPathNodes()->toArray());

?>