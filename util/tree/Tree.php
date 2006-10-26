<?php

/**
 * class for working with tree structures
 */
abstract class Tree extends ActiveRecord {	
	
	public $parentsInstance;
	
	private $children = array();	
	
	private static $instancesMap = array();

	private $class_name;
		
	public static function defineSchema($className = __CLASS__) {
				
		$schema = self::getSchemaInstance($className);		
		
		$schema->registerField(new ARPrimaryKeyField("ID", Integer::instance()));		
		$schema->registerField(new ARField("parent", Integer::instance()));					
		$schema->registerField(new ARField("lft", Integer::instance()));
		$schema->registerField(new ARField("rgt", Integer::instance()));		
	}		
	
	/**
	 * Gets new tree instance.
	 * @param string className
	 * @param null|int|Tree Parent tree or it's id. If null, has now parent.
	 * <code>
	 * $menu = Tree::getNewTreeInstance("Menu");
	 * ....
	 * $menu->Save();
	 * </code>
	 */
	public static function getNewTreeInstance($className, $parent = null) {
	  		  	
	  	$tree = ActiveRecord::GetNewInstance($className);	
		$tree->class_name = $className;	  	
	  	if ($parent != null) { 	  	
			
			if (is_Object($parent)) {
			  
			 	$tree->parent->set($parent->GetId()); 				 			  	
			} else {
			 
			 	$tree->parent->Set($parent); 				 	
			}		  	
		}	  	
	  	return $tree;  	
	}
	
	/**
	 * Gets name of table.
	 * @return string
	 */	
	protected function getTableName() {
	  	
	  	$schema = self::getSchemaInstance($this->class_name);
		return $schema->getName();
	}
	
	/**
	 * Saves instance to database. Also updates instances map.
	 */	
	public function save() {
		
		if (!$this->lft->hasValue()) {
		  
		  	$db = ActiveRecord::GetDbConnection();		  		  	
		  	
		 	if ($this->parent->get()) {
		 	  	
		 	  	if (!empty(self::$instancesMap[$this->parent->get()])) {
			
					$this->parentsInstance = self::$instancesMap[$this->parent->get()];
					$current_right = $this->parentsInstance->rgt->get();	 							  		     
				} else {
				  
				  	$res = $db->executeQuery("SELECT rgt FROM ".$this->getTableName()." WHERE id = ".$this->parent->get());				  
					$res->next();
					$current_right = (int)$res->getInt("rgt");		
				}	  						  
				  		  			  	
				$lft = $current_right;
			  	$rgt = $current_right + 1;
		
				$db->executeUpdate("UPDATE ".$this->getTableName()." SET lft = lft + 2 WHERE lft >= ".$current_right);
			    $db->executeUpdate("UPDATE ".$this->getTableName()." SET rgt = rgt + 2 WHERE rgt >= ".$current_right);	
			    
			    foreach (self::$instancesMap as $key => $tree) {
				 		 	
				 	if ($tree->lft->get() >= $current_right) {
					   
					   	$tree->lft->Set($tree->lft->get() + 2);
					} 
					
					if ($tree->rgt->get() >= $current_right) {
					  
					  	$tree->rgt->Set($tree->rgt->get() + 2);
					}
				}
				
			} else {
	   
			   	$res = $db->executeQuery("SELECT max(rgt) AS max FROM ".$this->getTableName());
				$res->next();				
				$max = (int)$res->getInt("max");		
	
				$lft = $max + 1;
				$rgt = $max + 2;
			} 			
			
			$this->lft->Set($lft);
		  	$this->rgt->Set($rgt);			  		  	
		
			ActiveRecord::save();		

			if ($this->parentsInstance != null) {
			  
				$this->SetParent($this->parentsInstance);	
			} else if (!empty(self::$instancesMap[0])) {
			  		  
			  	$this->setParent(self::$instancesMap[0]);			  	
			}

			self::$instancesMap[$this->getId()] = $this;
		} else {
				  
			ActiveRecord::save();
		}		
	}
	
	/**
	 * Modifies parent of tree.	 
	 * @param string $className
	 * @param int|Tree Tree or it's id
	 * @param null|int|Tree Parent tree or it's id. If null, tree will have no parent.
	 */
	public static function modifyTreeParent($className, $tree, $parent) {
	  
	  	$schema = self::getSchemaInstance($className);
		$table = $schema->getName();
		
		if (is_Object($tree)) {

		  	$tree_id = $tree->getId();		  	
		} else {
		  
		  	$tree_id = $tree;		  	
		}

		if (is_Object($parent)) {

	  		$parent_id = $parent->getId();						
	  	} else {
		    
	  		$parent_id = $parent;			
		}
		
		$db = ActiveRecord::GetDbConnection();	
						
		if (!empty(self::$instancesMap[$tree_id])) {
		  
		  	$tree_instance = self::$instancesMap[$tree_id];
		  	$current_left = $tree_instance->lft->get();
	  		$current_right = $tree_instance->rgt->get();	 
		} else {
		  
		  	$res = $db->executeQuery("SELECT lft, rgt FROM ".$table." WHERE id = ".$tree_id);			

			$res->next();			
			$current_left = (int)$res->getInt("lft");
		  	$current_right = (int)$res->getInt("rgt");
		}
		
		if (empty($parent_id)) {
			  
		 	$res = $db->executeQuery("SELECT max(rgt) AS max FROM ".$table);
			$res->next();				
			$parent_left = (int)$res->getInt("max") + 1;	 	
			$parent_right = (int)$res->getInt("max") + 2;	 					
		} else if (!empty(self::$instancesMap[$parent_id])) {
		  		  
		  	$parents_instance = self::$instancesMap[$parent_id];		  	
		  	$parent_left = $parents_instance->lft->get();
		  	$parent_right = $parents_instance->rgt->get();
		} else {

			$res = $db->executeQuery("SELECT lft, rgt FROM ".$table." WHERE id = ".$parent_id);				  
			$res->next();			
			$parent_left = (int)$res->getInt("lft");
			$parent_right = (int)$res->getInt("rgt");			
		}	  		  	
	  	 	  	
	  	$diff = $parent_right - $current_left;
	  	
	  	
	  	if ($diff > 0) {	  	
	  		
		  	$db->executeUpdate("UPDATE ".$table." SET lft = lft + ".$diff." WHERE lft >= ".$parent_right." OR ( lft >= ".$current_left." AND rgt <= ".$current_right." ) ");
			$db->executeUpdate("UPDATE ".$table." SET rgt = rgt + ".$diff." WHERE rgt >= ".$parent_right." OR ( lft >= ".$current_left." AND rgt <= ".$current_right." ) ");	
			
			foreach (self::$instancesMap as $key => $value) {
			  		  
			 	if ($value->lft->get() >= $parent_right ||
			 	 		($value->lft->get() >= $current_left && $value->rgt->get() <= $current_right)) {
						    
					$value->lft->set($value->lft->get() + $diff);		
				}
				if ($value->rgt->get() >= $parent_right ||
			 	 		($value->lft->get() >= $current_left && $value->rgt->get() <= $current_right)) {
						    
					$value->rgt->set($value->rgt->get() + $diff);		
				}
			}		
				  	
	  	} else {
	  	  	
			$diff2 = $current_right - $current_left + 1;			
			$diff3 = -$diff + $diff2;				
				    
		    $db->executeUpdate("UPDATE ".$table." SET lft = lft + ".$diff2." WHERE lft >= ".$parent_right);		    
			$db->executeUpdate("UPDATE ".$table." SET rgt = rgt + ".$diff2." WHERE rgt >= ".$parent_right);				
			$db->executeUpdate("UPDATE ".$table." SET lft = lft - ".$diff3.", rgt = rgt- ".$diff3."  WHERE lft >= ".($current_left + $diff2)." AND rgt <= ".($current_right + $diff2)."  ");
			
			foreach (self::$instancesMap as $key => $value) {
			  		  
			 	if ($value->lft->get() >= $parent_right) {
						    
					$value->lft->set($value->lft->get() + $diff2);		
				}
				if ($value->rgt->get() >= $parent_right) {
						    
					$value->rgt->set($value->rgt->get() + $diff2);		
				}
				if ($value->lft->get() >= $current_left + $diff2 
						&& $value->rgt->get() <= $current_right + $diff2) {
						    
					$value->lft->set($value->lft->get() - $diff3);		
					$value->rgt->set($value->rgt->get() - $diff3);		
				}
				
			}		
		}	  	

		$db->executeUpdate("UPDATE ".$table." SET parent = ".$parent_id."  WHERE id = ".$tree_id);	
		
		if (!empty($tree_instance)) {
		  
			$tree_instance->parent->set($parent_id); 	
			
			echo $tree_instance->name->get().' <br>--||--<br>';

			if (!empty($tree_instance->parentsInstance)) {		    
			
			    unset($tree_instance->parentsInstance->children[$tree_instance->getId()]);
			}		
			if (!empty($parents_instance)) {			  	
		
				$tree_instance->SetParent($parents_instance);	
			}
		}	  	
	}			
	
	/**
	 * Deletes tree from database. Updates instances map.
	 * @param string $className
	 * @param int|Tree Tree or it's id
	 */
	public static function delete($className, $tree) {
	  	if (is_object($tree)) {
		    			
			$id = $tree->getId();
		} else {
		  
		  	$id = $tree;
		}		
	  
		if (!empty(self::$instancesMap[$id])) {
			
			$tree = self::$instancesMap[$id];			
			Tree::_delete($className, $tree->lft->get(), $tree->rgt->get());	  
		} else {
		  				
			$tree = ActiveRecord::getInstanceById($className, $id, true);	 	
			Tree::_delete($className, $tree->lft->get(), $tree->rgt->get());

			//gal sitoj vietoj uztektu tiesiog istrinti recordseta ir viskas		
			/*$filter = new ArDeleteFilter();		
			$filter->setCondition(" lft >= ".$tree->lft->get()." AND rgt <= ".$tree->rgt->get());			
			ActiveRecord::deleteRecordSet($className, $filter);*/			
		}  		
	}	
	
	protected static function _delete($className, $lft, $rgt) {

		$filter = new ArDeleteFilter();		
		
		$cond = new EqualsOrMoreCond($className.".lft", $lft);
		$cond->addAND(new EqualsOrLessCond($className.".rgt", $rgt));
		$filter->setCondition($cond);
		
		//$filter->setCondition(" lft >= ".$lft." AND rgt <= ".$rgt);
		
		
		
		ActiveRecord::deleteRecordSet($className, $filter);
		
		foreach (self::$instancesMap as $key => $child) {
		
			if ($child->lft->get() >= $lft && $child->rgt->get() <= $rgt) {
			  
			 	unSet(self::$instancesMap[$child->getId()]);	  	 
			 	if (!empty($child->parentsInstance)) {		    
		
				    unset($child->parentsInstance->children[$child->getId()]);
				}
			}
		
		}	  	
	}
		
	/**
	 * Get tree by id.
	 * @param string $className
	 * @param int $id 
	 * @return Tree
	 */
	public static function getTreeInstanceById($className, $id) {
		
		if (!empty(self::$instancesMap[$id])) {
		  	
		  	return self::$instancesMap[$id];
		}				
		
		$tree = ActiveRecord::getInstanceById($className, $id, true);	
	
		$filter = new ArSelectFilter();		
		//$filter->setCondition(" lft >= ".$tree->lft->get()." AND rgt <= ".$tree->rgt->get());

		$cond = new EqualsOrMoreCond($className.".lft", $tree->lft->get());
		$cond->addAND(new EqualsOrLessCond($className.".rgt", $tree->rgt->get()));
		$filter->setCondition($cond);
		
		$filter->setLimit(10000000);
		$filter->setOrder("lft");
		$tree_set = ActiveRecord::getRecordSet($className, $filter, true);	  			
							
		foreach ($tree_set as $value) {
			
			
			$parent_id = $value->parent->get();
			if (!empty($parent_id) && !empty(self::$instancesMap[$parent_id])) {
			  
				$value->setParent(self::$instancesMap[$parent_id]);
			}
						
			self::$instancesMap[$value->getId()] = $value;		
		}				
		
		return $tree;	
	}
			
	/**
	 * Gets all tree set.
	 * @param string $className
	 * @return Tree with id paramater 0.
	 */	
	public static function getAllTree($className, $loadReferencedRecords = false) {
		
		if (!empty(self::$instancesMap[0])) {
		  	
		  	return self::$instancesMap[0];
		}
		
		self::$instancesMap[0] = ActiveRecord::getInstanceByID($className, 0);		
		
		$filter = new ARSelectFilter();
		$filter->setLimit(10000000);
		$filter->setOrder("lft");
		$tree_set = ActiveRecord::getRecordSet($className, $filter, true, $loadReferencedRecords);					
		
		foreach ($tree_set as $value) {
					
			if (!empty(self::$instancesMap[$value->getId()])) {
			  
				$value = self::$instancesMap[$value->getId()];
			}
						
			$parent_id = $value->parent->get();
			if (!empty($parent_id) && !empty(self::$instancesMap[$parent_id])) {
			  
				$value->setParent(self::$instancesMap[$parent_id]);
			} else {
							
			  	$value->setParent(self::$instancesMap[0]);
			}
						
			self::$instancesMap[$value->getId()] = $value;	
		}	
		
		return self::$instancesMap[0];
	}			
		
	/**
	 * Sets parent of tree.
	 * @param $parent Tree Parent tree
	 */				
	protected function setParent($parent) {
	  	  	
		$this->parentsInstance = $parent; 				
		$parent->children[$this->getId()] = $this; 				
	}
		
	/** 
	 * Gets count of children.
	 */
	public function getChildrenCount() {
	  
	  	return count($this->children);
	}
	
	/**
	 * Gets children array
	 */
	public function getChildren() {
	  
	  	return $this->children;
	}	
	
	/**
	 *
	 */
	public function getArray(&$array = array(), &$start = 0, $depth = 0) {

		if ($start === 0) {

		  	$array[$start] = $this->toArray();
			$array[$start]['depth'] = $depth;				
			$array[$start]['children_count'] = $this->getChildrenCount();
			$depth ++;
			$start ++;
		}

	  	foreach ($this->getChildren() as $child) {
			
			$array[$start] = $child->toArray();
			$array[$start]['depth'] = $depth;				
			$array[$start]['children_count'] = $child->getChildrenCount();	
			$start ++;	  				
			
			if ($child->getChildrenCount() > 0) {

			  	$child->getArray(&$array, &$start, $depth + 1);
			}
		}	  
		
		if ($depth === 1) {

			return $array;
		}
	}
}

?>