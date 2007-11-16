<?php

require_once(dirname(dirname(dirname(__FILE__)))."/ActiveRecord.php");

/**
 *
 * @package activerecord.util.generator 
 * @author Integry Systems
 */
class ARSQLGenerator {
	
	private static $sql_generator;
	 
	private $conn;
	
	public function __construct($conn) {
	  
	  	$this->conn = $conn;
	}
	
	/**
	* Decides which generator class return and returns ist Singleton instance.
	* @param Connection $conn
	* @return ARSQLGenerator
	*/	   	
	public static function getInstance($conn) {
	  	  	
	  	$dsn = $conn->getDsn();
	  	$type = $dsn['phptype'];
	  	
	  	if (empty(self::$sql_generator[$type])) {
			
			$file_name = dirname(__FILE__)."\drivers\\".$type."ARSQLGenerator.php";
			
			if (file_exists($file_name)) {

				require_once($file_name);  
				$class_name = $type."ARSQLGenerator";
				self::$sql_generator[$type] = new $class_name($conn);							  
			} else {
			
		  	   self::$sql_generator[$type] = new ARSQLGenerator($conn);			
			}	  	
		}
		self::$sql_generator[$type]->conn = $conn;		
		return self::$sql_generator[$type];
	}
	
	/**
	* Creates table.
	* @param string $name Table name
	* @param bool $drop If true, deletes table if it exists
	* @todo Delete
	*/
	public function createTable($name, $drop = false) {
	  	  
	  	$schema = ActiveRecord::getSchemaInstance($name);
	  	$table_name = $schema->getName();
	  		  		  	
		$db_info = $this->conn->getDatabaseInfo();							
		$db_info->getTables();
		
		if (!$db_info->hasTable($name)) {
		  
		  	$this->conn->executeUpdate($this->generateTableDDL($name));
		} else if ($drop) {
		  
		  	$this->conn->executeUpdate("DROP TABLE ".$name);
		  	$this->conn->executeUpdate($this->generateTableDDL($name));	
		}		
	}
	
			
  	/**
  	* Generates tables DDL.
  	* @param string $name Models name
  	* @return string
  	*/
  	public function generateTableDDL($name, $intent = "") {
  	  			
		return $this->_generateTableDDL($name, $intent);	
	}	
	
	/**
	* Gets from database table structure and models schema. If they don't ?????, creates table modify sql.
	* @param string $name Models name
	* @param bool $drop Parameter shows if it will drop not used columns
	* @param string $intent Just for show in display
  	* @return string
	*/
	public function generateTableModify($name, $drop = false, $intent = "\n") {
	  
	  	$comma = ", ".$intent;
	  	$schema = ActiveRecord::getSchemaInstance($name);
	  	$table_name = $schema->getName();
	  	$fields_list = $schema->getFieldList();
	  		  	
		$db_info = $this->conn->getDatabaseInfo();							
		$table_info = $db_info->getTable($table_name);
		
		foreach ($table_info->getColumns() as $column) {
		  		  	
		  	$cols[strtolower($column->name)] = $column;	  			  
		}		
				
		$sql = "";
		foreach ($fields_list as $field) {
		  
		  	if (empty($cols[strtolower($field->getName())])) {
				
				$sql .= " ADD ".$field->getName()." ".$this->_defineField($field, false).$comma;	
				
				//foreign key
			  	if ($field instanceof ARForeignKeyField) {
					
					$sql .= " ADD FOREIGN KEY ( ".$field->getName()." ) REFERENCES ".$field->getForeignTableName()."( ".$field->getForeignFieldName()." ) ".$comma;
				}
			} else {
			  			  	
				$exist[strtolower($field->getName())] = true;
				$column = $cols[strtolower($field->getName())];
			
				if ($field instanceof ARPrimaryKeyField && 
					$column->isAutoIncrement) {
				  	
				  	continue;
				}
			
				if (strtolower($this->_defineField($field)) == $this->_sqlFromColumn($column)) {
				  
				  	continue;
				}
				
				$sql .= $this->_modifyStatement($field)." ".$this->_defineField($field, false, false).$comma;
				/*echo "\n";
				print_r($this->_sqlFromColumn($column));
				echo "\n";
				echo "---\n";*/
			}
		}	
		
		if ($drop) {
		  
		  	foreach ($table_info->getColumns() as $column) {
				
				if (empty($exist[strtolower($column->name)])) {
				  
					$sql .= " DROP ".$column->name.$comma;	  
				}
			}
		}

		if (!empty($sql)) {
		  
			$sql = "ALTER TABLE ".$table_name." ".substr($sql, 0, -strlen($comma));	
		}
		
		return $sql;
	}	
	
	protected function _modifyStatement($field) {
		
		return " MODIFY ".$field->getName();
	}
	
	protected function _generateTableDDL($name, $intent = "") {
	  	
	  	$comma = ", ".$intent;	
  		$schema = ActiveRecord::getSchemaInstance($name);
  		
		$table_name =	$schema->getName();
		$field_list = $schema->GetFieldList();
		$primary_list = $schema->getPrimaryKeyList();
		$foreign_list = $schema->getForeignKeyList();
		
		if (count($field_list) < 1) {
		  
		  	return null;
		}
		
		if (count($primary_list) > 1) {
		  
		  	$auto_increment = false;
		} else {
		  	
		  	$auto_increment = true;
		}
		
		$sql = "CREATE TABLE ".$table_name." ( ".$intent;
		
		// fields
		foreach ($field_list as $field) {
		  
		  	$sql .= $field->getName()." ".$this->_defineField($field, $auto_increment).$comma;	  	
		  	
		  	//foreign key
		  	if ($field instanceof ARForeignKeyField) {
				
				$sql .= " FOREIGN KEY ( ".$field->getName()." ) REFERENCES ".$field->getForeignTableName()."( ".$field->getForeignFieldName()." ) ".$comma;
			}
		}
		
		//primary keys
	   	if (count($primary_list) > 0) {
			 
			$sql .= " PRIMARY KEY ( ";
			
			foreach ($primary_list as $primary) {
			  
			  	$sql .= $primary->getName().$comma; 
			}
			
			$sql = substr($sql, 0, -strlen($comma));	 				
			$sql .= " )".$comma;	 
		}				
		
		$sql = substr($sql, 0, -strlen($comma));	 
		
		return $sql." )";  
	}
	
	protected function _defineField(ARField $field, $auto_increment = true) {

		$sql = '';
		
		$type = $field->getDataType();
		$type_name = strtolower(get_class($field->getDataType()));
		$length_value = $type->getLength();
		
		if (!empty($length_value)) {
		  	
		 	$length = "(".$length_value.") " ;
		} else {
		  
		  	$length = " ";
		}
		
	
		if ($length_value > 255 && ($type_name == 'char' ||$type_name == 'varchar')) {
		  
		  	$sql = 'text ';	
		} else {
				
			switch ($type_name) {
			
				case 'bool':
				
					$sql .= "integer(1) ";
				break;	
				
				case 'float':
					
					$sql .= "float ";
				break;
			
				case 'char':
				case 'binary':
					
					$sql .= 'var'.$type_name.$length;
				break;
			
				default:  //integer, varchar, time, datetime, date
	
					$sql .= $type_name.$length;
				break;
			}				
		}
		
		$sql .= "NOT NULL ";
		
		if ($auto_increment && $field instanceof ARPrimaryKeyField) {
		  
		  	$sql .= "AUTO_INCREMENT ";
		} 
		
		return $sql;
	}
	
	protected function _sqlFromColumn(ColumnInfo $column, $auto_increment = true) {
	  
  		$sql = ''; 	
  		
  		$length = $column->size;
  		if (!empty($length)) {
		   
			$length = "(".$length.") " ;
		} else {
		  
		  	$length = " ";
		}

  		switch ($column->nativeType) {
			
			case 'int':
				
				$sql .= "integer".$length;
			break;
			
			case 'char':
			
				$sql .= "char".$length;
			break;
			
			case 'bool':
			
			break;
			
			default:
			
				$sql .=	$column->nativeType.$length;
			break;
		}
		
		$sql .= "NOT NULL ";
		
		return strtolower($sql);
	}
	
	
	
}

?>