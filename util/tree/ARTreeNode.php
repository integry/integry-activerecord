<?php

/**
 * Enter description here...
 *
 * @link http://www.sitepoint.com/article/hierarchical-data-database/
 * @author Saulius Rupainis <saulius@integry.net>
 * @package activerecord.util.tree
 */
class ARTreeNode extends ActiveRecord
{
	const LEFT_NODE_FIELD_NAME = 'lft';
	const RIGHT_NODE_FIELD_NAME = 'rgt';
	const PARENT_NODE_FIELD_NAME = 'parentNodeID';
	const ROOT_ID = 0;
	
	private $childList = array();
	private $isChildNodeListLoaded = false;
	
	public static function getInstanceByID($className, $recordID, $loadRecordData = false, $loadReferencedRecords = false, $loadChildRecords = false)
	{
		$instance = parent::getInstanceByID($className, $recordID, $loadRecordData, $loadReferencedRecords);

		if ($loadChildRecords)
		{
			$instance->loadChildNodes();
		}
		return $instance;
	}
	
	public static function getNewInstance($className, ARTreeNode $parentNode)
	{
		$instance = parent::getNewInstance($className);
		$instance->setParentNode($parentNode);
		return $instance;
	}
	
	public function loadChildNodes()
	{
		$className = get_class($this);
		$nodeFilter = new ARSelectFilter();
		$cond = new OperatorCond(new ArFieldHandle($className, self::LEFT_NODE_FIELD_NAME), $this->getField(self::LEFT_NODE_FIELD_NAME)->get(), ">");
		$cond->addAND(new OperatorCond(new ArFieldHandle($className, self::RIGHT_NODE_FIELD_NAME), $this->getField(self::RIGHT_NODE_FIELD_NAME)->get(), "<"));
		$nodeFilter->setCondition($cond);
		$nodeFilter->setOrder(new ArFieldHandle($className, self::LEFT_NODE_FIELD_NAME));
		
		$childList = ActiveRecord::getRecordSet($className, $nodeFilter, $loadReferencedRecords);
		$indexedNodeList = array();
		$indexedNodeList[$this->getID()] = $this;
			
		foreach ($childList as $child)
		{
			$nodeId = $child->getID();
			$indexedNodeList[$nodeId] = $child;
		}
		foreach ($childList as $child)
		{
			echo $child->name->get() . "\n";
			$parentId = $child->getParentNode()->getID();
			$indexedNodeList[$parentId]->registerChildNode($child);
		}
		$this->isChildNodeListLoaded = true;
	}
	
	public function getChildNodeList()
	{
		if (!$this->isChildNodeListLoaded)
		{
			$this->loadChildNodes();
		}
		return $this->childList;
	}
	
	public function save()
	{
		$tableName = self::getSchemaInstance(get_class($this))->getName();
		if (!$this->hasID())
		{
			// Inserting new node
			$parentRightValue = '';
			$nodeLeftValue = $parentRightValue;
			$nodeRightValue = $nodeLeftValue + 1;
			
			$this->db->executeUpdate("UPDATE " . $tableName . " SET " . self::RIGHT_NODE_FIELD_NAME . " = "  . self::RIGHT_NODE_FIELD_NAME . " + 2 WHERE "  . self::RIGHT_NODE_FIELD_NAME . ">=" . $parentRightValue);
			$this->db->executeUpdate("UPDATE " . $tableName . " SET " . self::LEFT_NODE_FIELD_NAME . " = "  . self::LEFT_NODE_FIELD_NAME . " + 2 WHERE "  . self::LEFT_NODE_FIELD_NAME . ">=" . $parentRightValue);
			
			$this->getField(self::RIGHT_NODE_FIELD_NAME)->set($nodeRightValue);
			$this->getField(self::LEFT_NODE_FIELD_NAME)->set($nodeLeftValue);
		}
		parent::save();
	}
	
	public function registerChildNode(ARTreeNode $childNode)
	{
		$this->childList[] = $childNode;
	}
	
	public function setParentNode(ARTreeNode $parentNode)
	{
		$this->getField(self::PARENT_NODE_FIELD_NAME)->set($parentNode);
	}
	
	public function getParentNode()
	{
		return $this->getField(self::PARENT_NODE_FIELD_NAME)->get();
	}
	
	public static function getRootNode($className)
	{
		return self::getInstanceByID($className, self::ROOT_ID);
	}
	
	public function getPathNodes()
	{
		
	}

	public function toArray()
	{
		$data = array();
		foreach ($this->data as $name => $field)
		{
			if ($name == self::PARENT_NODE_FIELD_NAME)
			{
				$data['parent'] = $field->get()->getID();
			}
			else
			{
				$data[$name] = $field->get();
			}
		}
		$childArray = array();
		foreach ($this->childList as $child)
		{
			$childArray[] = $child->toArray();	
		}
		$data['children'] = $childArray;
		return $data;
	}
	
	public static function defineSchema($className = __CLASS__) 
	{			
		$schema = self::getSchemaInstance($className);
		$tableName = $schema->getName();		
		$schema->registerField(new ARPrimaryKeyField("ID", Integer::instance()));	
		$schema->registerField(new ARForeignKeyField(self::PARENT_NODE_FIELD_NAME, $tableName, "ID",$className, Integer::instance()));					
		$schema->registerField(new ARField(self::LEFT_NODE_FIELD_NAME, Integer::instance()));
		$schema->registerField(new ARField(self::RIGHT_NODE_FIELD_NAME, Integer::instance()));		
	}
}

?>