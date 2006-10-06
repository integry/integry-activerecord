<?php

if(strpos(get_include_path(), dirname(__FILE__)) === false) {
	set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));
}

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . "schema" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . "query" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . "query" . DIRECTORY_SEPARATOR . "filter" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . "schema" . DIRECTORY_SEPARATOR . "datatype" . DIRECTORY_SEPARATOR);

if(!function_exists("__autoload")) {
	function __autoload($className) {
		@include_once $className.'.php';
	}
}

require_once("schema/datatype/ARSchemaDataType.php");
include_once("query/filter/Condition.php");

function __invokeStaticMethod($className, $methodName, $paramList = array()) {
	$method = new ReflectionMethod($className, $methodName);

	return $method->invokeArgs(null, $paramList);
}

/**
 * 
 * Database record base class 
 * 
 * You must implement self::defineSchema() in every new subclass
 * 
 * <code>
 * MyModel extends ActiveRecord
 * {
 * 		public static function defineSchema($className = __CLASS__) {
 * 			// loading schema object to work with
 * 			$schema = self::getSchemaInstance($className);
 * 			$schema->setName("MyTable");
 * 			$schema->registerField(new ARField("myFieldName"));
 * 			
 * 			// ...
 * 		}
 * }
 * </code>
 *
 * @see ARField
 * @see ARPrimaryKeyField
 * @see ARForeignKeyField
 * 
 * @package activerecord
 * 
 * @todo methods for setting self::$creolePath
 *
 */
abstract class ActiveRecord {
	
	/**
	 * Database connection object
	 *
	 * @var Creole
	 */
	private static $dbConnection = null;
	
	/**
	 * DSN string for a database connection (Database Source Name)
	 * (for using in static context)
	 * 
	 * @var string
	 */
	private static $dsn = "";
	
	/**
	 * Schema mapper
	 *
	 * @var ARSchemaMap
	 */
	protected static $schemaMap = null;
	
	private static $recordPool = null;
	
	/**
	 * Path to a Creole library
	 *
	 * @var string
	 */
	public static $creolePath = "";
	
	/**
	 * Database connection instance (refererences to self::$dbConnection)
	 * 
	 * @see self::$dbConnection
	 * @var Creole
	 */
	protected $db = null;
	
	/**
	 * Schema of this instance (defines a record structure)
	 *
	 * @var ARSchema
	 */
	private $schema = null;

	/**
	 * Record data
	 *
	 * @var ARValueMapper[]
	 */
	private $data = array();
	
	/**
	 * A helper const which should be used as a "magick number" to load referenced records
	 *
	 * @see self::getRecordSet()
	 *
	 */
	const LOAD_REFERENCES = true;
	
	/**
	 * A helper const to use as a "magick number" to indicate that record data shoul be loaded from a database immediately
	 * @see self::getInstanceByID()
	 *
	 */
	const LOAD_DATA = true;
	
	const PERFORM_INSERT = 1;
	const PERFORM_UPDATE = 2;
	
	/**
	 * Is record data loaded from a database?
	 *
	 * @var bool
	 */
	protected $isLoaded = false;
	
	public static $logger = null;

	/**
	 * ActiveRecord constructor. Never use it directly
	 * 
	 * @see self::getNewInstance()
	 * @see self::getInstanceByID()
	 */
	protected function __construct() {
		
		$this->schema = self::getSchemaInstance(get_class($this));
		$this->createDataAccessVariables();
	}
	
	/**
	 * Creates data containers and instance variables to directly access ValueContainers 
	 * (record field data)
	 * 
	 * Record fields type of PKField has no direct access (instance variables are not created)
	 *
	 */
	private function createDataAccessVariables() {
		
		$fieldList = $this->schema->getFieldList();
		
		foreach ($fieldList as $name => $field) {

			$this->data[$name] = new ARValueMapper($field);
			if($field instanceof ARForeignKey) {
				
				$varName = $field->getForeignClassName();
				// Making first letter lowercase
				$varName = strtolower(substr($varName, 0, 1)) . substr($varName, 1);
				$this->$varName = $this->data[$name];
				
			} else if(!($field instanceof ARPrimaryKey)) {
				$this->$name = $this->data[$name];
			}
		}
	}
	
	/**
	 * Creates or gets an already created Schema object for a given class $className
	 *
	 * @param string $className
	 * @return Schema
	 */
	public static function getSchemaInstance($className) {
		
		if(empty(self::$schemaMap[$className])) {
			self::$schemaMap[$className] = new ARSchema();
			
			/* Using PHP5 reflection api to call a static method of $className class */
			$staticDefMethod = new ReflectionMethod($className, 'defineSchema');
			$staticDefMethod->invoke(null);
			/* end block */
			
			if (!self::$schemaMap[$className]->isValid()) {
				throw new ARException("Invalid schema (" .$className. ") definition! Make sure it has a name assigned and fields defined (record structure)");
			}
		}
		return self::$schemaMap[$className];
	}
	
	/**
	 * Prepares a database connection for use.
	 * 
	 * This method involves some kind of optimisation: database connection library 
	 * source is loaded and connection instance is created only when it is really needed
	 *
	 */
	private function setupDBConnection() {
		$this->db = self::getDBConnection();
	}
	
	/**
	 * Return a database connection object
	 *
	 * @return Creole db object
	 */
	public static function getDBConnection() {
		
		if(!self::$dbConnection) {
			set_include_path(get_include_path() . PATH_SEPARATOR . self::$creolePath);
			require_once("creole" . DIRECTORY_SEPARATOR . "Creole.php");
			
			self::$dbConnection = Creole::getConnection(self::$dsn);
			self::getLogger()->logAction("Creating a database connection");
		}
		return self::$dbConnection;
	}
	
	/**
	 * Sets a primary key value for a record
	 *
	 * @param mixed $recordID PK value, or array if PK consists of multiple fields (form: array(fieldName => value, fieldName2 -> value2, ...))
	 */
	public function setID($recordID, $markAsModified = true) {
		$PKList = $this->schema->getPrimaryKeyList();
		
		if (!is_array($recordID)) {
			if (count($PKList) == 1) {
				$PKFieldName = key($PKList);
				
				//if ()
				if ($this->data[$PKFieldName]->get() instanceof ARForeignKey) {
					$instance = ActiveRecord::getInstanceByID($this->schema->getField($PKFieldName)->getForeignClassName(), $recordID);
					$this->data[$PKFieldName]->set($instance, $markAsModified);
				} else {
					$this->data[$PKFieldName]->set($recordID, $markAsModified);
				}
				
			} else {
				throw new ARException("Primary key consists of more than one field (recordID parameter must be an associative array)");
			}
		} else {
			if (count($recordID) == count($PKList)) {
				foreach ($recordID as $name => $value) {
					if($this->schema->fieldExists($name)) {
						//$this->data[$name] = $value;
						$instance = ActiveRecord::getInstanceByID($this->schema->getField($name)->getForeignClassName(), $value);
						$this->data[$name]->set($instance, $markAsModified);
					} else {
						throw new ARException("No such primary key field: " . $name . " (schema: " . $this->schema->getName() . ")");
					}
				}
			} else {
				throw new ARException("Unknown situation (not implemented?)");
			}
		}
	}
	
	/**
	 * Returns a primary key value of record
	 *
	 * @param mixed $recordID
	 * @return mixed record id. If primary key consists of more than one field an array is returned
	 */
	public function getID() {
		$PKList = $this->schema->getPrimaryKeyList();
		$PK = array();
		foreach ($PKList as $name => $field) {
			if($field instanceof ARPrimaryForeignKeyField) {
				$PK[$name] = $this->data[$name]->get()->getID();
			} else {
				$PK[$name] = $this->data[$name]->get();
			}
		}
		if (count($PK) == 1) {
			return $PK[key($PK)];
		} else {
			return $PK;
		}
	}
	
	/**
	 * Creates a new instance of record
	 * 
	 * Use this method when you are going to create a new persistent object. If you 
	 * need to load an existing one call self::getInstanceByID()
	 * 
	 * @see self::getInstanceByID()
	 * @param string $className
	 * @return ActiveRecord
	 */
	public static function getNewInstance($className) {
		$obj = new  $className();
		//self::getLogger()->logObject($obj);
		return $obj;
	}
	
	/**
	 * Sets a DSN (database connection configuration info)
	 *
	 * @param string $dsn
	 */
	public static function setDSN($dsn) {
		self::$dsn = $dsn;
	}
	
	/**
	 * Gets an existing record instance (persisted on a database). 
	 * 
	 * Object representing concrete record gets created only once (Flyweight pattern)
	 *
	 * @param string $className Class representing record
	 * @param mixed $recordID
	 * @param bool $loadRecordData
	 * @param bool $loadReferencedRecords
	 * 
	 * @link http://en.wikipedia.org/wiki/Flyweight_pattern
	 * 
	 * @return ActiveRecord
	 */
	public static function getInstanceByID($className, $recordID, $loadRecordData = false, $loadReferencedRecords = false) {
		
		$fromPool = false;
		$instance = self::retrieveFromPool($className, $recordID);
		if ($instance == null) {
			$instance = self::getNewInstance($className);
			$instance->setID($recordID, false);
			self::storeToPool($instance);
			$fromPool = true;
		}
		
		if ($loadRecordData) {
			$instance->load($loadReferencedRecords);
		}
		
		self::getLogger()->logObject($instance, $fromPool);
		return $instance;
	}
	
	/**
	 * Stores ActiveRecord subclass instance in a record pool
	 *
	 * @param ActiveRecord $instance
	 */
	private static function storeToPool(ActiveRecord $instance) {
		$hash = self::getRecordHash($instance->getID());
		$className = get_class($instance);
		self::$recordPool[$className][$hash] = $instance;
	}
	
	/**
	 * Retrieves ActiveRecord subclass instance from a record pool
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * @return ActiveRecord Instance of requested object or null if object is not stored in a pool
	 */
	private static function retrieveFromPool($className, $recordID) {
		$hash = self::getRecordHash($recordID);
		if(!empty(self::$recordPool[$className][$hash])) {
			return self::$recordPool[$className][$hash];
		} else {
			return null;
		}
	}
	
	/**
	 * Gets a unique string representinh concrete record
	 *
	 * @param mixed $recordID
	 * @return string
	 */
	private static function getRecordHash($recordID) {
		if (is_array($recordID)) {
			asort(&$recordID);
			$hashElements = array();
			foreach ($recordID as $value) {
				$hashElements[] = $value;
			}
			return implode("-", $hashElements);
		} else {
			return $recordID;
		}
	}
	
	/**
	 * Creates a select query object for a table identified by an ActiveRecord class name
	 *
	 * @param string $className
	 * @param bool $loadReferencedRecords Join records on foreign keys?
	 * @return string
	 */
	public static function createSelectQuery($className, $loadReferencedRecords = false) {
		
		$schema = self::getSchemaInstance($className);
		$query = new ARSelectQueryBuilder();
		
		$query->includeTable($schema->getName());
		
		/* Adding main table fields to a select query */
		$fieldList = $schema->getFieldList();
		foreach ($fieldList as $field) {
			$query->addField($field->getName(), $schema->getName());
		}
		
		$referenceList = $schema->getForeignKeyList();
		if ($loadReferencedRecords && !empty($referenceList)) {
			foreach ($referenceList as $name => $field) {
				
				$foreignClassName  = $field->getForeignClassName();
				$foreignSchema = self::getSchemaInstance($foreignClassName);
				$query->joinTable($foreignSchema->getName(), $schema->getName(), $field->getForeignFieldName(), $field->getName());
				
				$foreignFieldList = $foreignSchema->getFieldList();
				$foreignTableName = $foreignSchema->getName();
				foreach ($foreignFieldList as $foreignField) {
					$query->addField($foreignField->getName(), $foreignTableName, $foreignTableName . "_" . $foreignField->getName());
				}
			}
		}
		return $query;
	}
	
	/**
	 * Loads and sets persisted record data from a database
	 *
	 * @param bool $loadReferencedRecords
	 */
	public function load($loadReferencedRecords = false) {
		
		if($this->isLoaded) {
			return;
		}
		$query = self::createSelectQuery(get_class($this), $loadReferencedRecords);
		$this->loadData($loadReferencedRecords, $query);
	}
	
	protected final function loadData($loadReferencedRecords, ARSelectQueryBuilder $query) {
		
		$PKList = $this->schema->getPrimaryKeyList();
		$PKCond = null;
		foreach ($PKList as $PK) {
			
			if ($PK instanceof ARForeignKey) {
				$PKValue = $this->data[$PK->getName()]->get()->getID();
			} else {
				$PKValue = $this->data[$PK->getName()]->get();
			}
			if($PKCond == null) {
				//$PKCond = new EqualsCond(get_class($this).".".$PK->getName(), $this->data[$PK->getName()]->get());
				$PKCond = new EqualsCond(new ARFieldHandle(get_class($this), $PK->getName()), $PKValue);
			} else {
				//$PKCond->addAND(new EqualsCond(get_class($this).".".$PK->getName(), $this->data[$PK->getName()]->get()));
				$PKCond->addAND(new EqualsCond(
									new ARFieldHandle(get_class($this), 
													  $PK->getName()
													  ), 
									$PKValue)
								);
			}
		}

		$query->getFilter()->mergeCondition($PKCond);
		$rowDataArray = self::fetchDataFromDB($query);
		
		if (empty($rowDataArray)) {
			throw new ARNotFoundException(get_class($this), $this->getID());
		}
		if (count($rowDataArray) > 1) {
			throw new ARException("Unexpected behavior: got more than one record from a database while loading single instance data");
		}
		
		$parsedRowData = self::prepareDataArray(get_class($this), $rowDataArray[0], $loadReferencedRecords);
		$this->setData($parsedRowData['recordData'], $parsedRowData['referenceData']);
		if (!empty($parsedRowData['miscData'])) {
			$this->miscRecordDataHandler($parsedRowData['miscData']);
		}
	}
	
	
	/**
	 * Extracts a primary key value (record ID) from a data array
	 *
	 * @param string $className
	 * @param array $dataArray
	 * @return mixed
	 */
	protected static function extractRecordID($className, $dataArray) {

		$schema = self::getSchemaInstance($className);
		$PKList = $schema->getPrimaryKeyList();
		$recordID = null;
			
		foreach ($PKList as $name => $field) {
			if(count($PKList) > 1) {
				$recordID[$name] = $dataArray[$name];
			} else {
				$recordID = $dataArray[$name];
			}
		}
		return $recordID;
	}
	
	/**
	 * Parse raw record data and separate it in 3 different parts: 
	 * 1. record data 
	 * 2. data of record references
	 * 3. misc data
	 *
	 * @param string $className
	 * @param array $dataArray
	 */
	public static function prepareDataArray($className, $dataArray, $loadReferencedRecords = false) {
		
		$referenceListData = array();
		$recordData = array();
		$miscData = array();
		
		$schema = self::getSchemaInstance($className);
		
		$fieldList = $schema->getFieldList();
		foreach ($fieldList as $name => $field) {
			$recordData[$name] = $dataArray[$name];
			unset($dataArray[$name]);
		}
		
		if($loadReferencedRecords) {
			$referenceList = $schema->getForeignKeyList();
					
			foreach ($referenceList as $name => $field) {
				$foreignClassName = $field->getForeignClassName();
				$referenceListData[$foreignClassName] = array();
				$refSchema = self::getSchemaInstance($foreignClassName);
				
				foreach ($refSchema->getFieldList() as $field) {
					$referenceListData[$foreignClassName][$field->getName()] = $dataArray[$refSchema->getName() . "_" . $field->getName()];
					unset($dataArray[$refSchema->getName() . "_" . $field->getName()]);
				}
			}
		}
		$miscData = $dataArray;

		return array("recordData" => $recordData, "referenceData" => $referenceListData, "miscData" => $miscData);
	}
	
	protected function miscRecordDataHandler($miscRecordDataArray) {
		throw new ARException("miscRecordDataHandler is not implemented");
	}
	
	/**
	 * Sets initial record data and marks it as loaded
	 *
	 * @param array $recordDataArray Record data (assoc array)
	 * @param array $referencedRecordData Referenced record data
	 * 
	 * @todo optimise recursive setData() calls, to avoid repeated record data setting
	 */
	protected function setData($recordDataArray, $referencedRecordData = array()) {

		$fieldNameList = array_keys($recordDataArray);
		foreach ($fieldNameList as $fieldName) {

			$field = $this->schema->getField($fieldName);
			
			if ($field instanceof ARForeignKey) {
				$className = $field->getForeignClassName();
				$instance = ActiveRecord::getInstanceByID($className, $recordDataArray[$fieldName]);
				if (!empty($referencedRecordData)) {
					$instance->setData($referencedRecordData[$className]);
				}
				$this->data[$fieldName]->set($instance, false);
			} else {
				$this->data[$fieldName]->set($recordDataArray[$fieldName], false);
			}

		}
		$this->isLoaded = true;
	}
	
	/**
	 * Loads a set of active record instances (persisted object list) by using a filter
	 *
	 * @param string $className
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords
	 * 
	 * @todo Smarter way to merge filters (from a query object and the one that is supplied as parameter)
	 */
	public static function getRecordSet($className, ARSelectFilter $filter, $loadReferencedRecords = false) {

		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);
		
		return self::createRecordSet($className, $query, $loadReferencedRecords);
	}
	
	protected static function fetchDataFromDB(ARSelectQueryBuilder $query) {
		
		$db = self::getDBConnection();
		$queryStr = $query->createString();
		
		self::getLogger()->logQuery($queryStr);
		$resultSet = $db->executeQuery($queryStr);
		$dataArray = array();
		while ($resultSet->next()) {
			$dataArray[] = $resultSet->getRow();
		}
		return $dataArray;
	}
	
	public static function getRecordSetByQuery($className, ARSelectQueryBuilder $query) {	
		return self::createRecordSet($className, $query);
	}
	
	protected final static function createRecordSet($className, ARSelectQueryBuilder $query, $loadReferencedRecords = false) {
		
		$schema = self::getSchemaInstance($className);

		$queryResultData = self::fetchDataFromDB($query);
		$recordSet = new ARSet($query->getFilter());
		
		foreach ($queryResultData as $rowData) {

			$recordID = self::extractRecordID($className, $rowData);
			$instance = self::getInstanceByID($className, $recordID);
			$recordSet->add($instance);
			
			$parsedRowData = self::prepareDataArray($className, $rowData, $loadReferencedRecords);
			$instance->setData($parsedRowData['recordData'], $parsedRowData['referenceData']);
			if (!empty($parsedRowData['miscData'])) {
				$instance->miscRecordDataHandler($parsedRowData['miscData']);
			}
		}
		
		$filter = $query->getFilter();
		if ($filter->getLimit() >= $recordSet->size() || $filter->getOffset() > 0) {
			
			$db = self::getDBConnection();
			$counterFilter = clone $filter;
			$counterFilter->setLimit(0, 0);
			
			$query->removeFieldList();
			$query->addField("COUNT(*)", null, "totalCount");
			$query->setFilter($counterFilter);
			
			//$counterQuery = "SELECT COUNT(*) AS totalCount FROM " . $schema->getName() . " " . $counterFilter->createString();
			$counterQuery = $query->createString();
			
			self::getLogger()->logQuery($counterQuery);
			$counterResult = $db->executeQuery($counterQuery);
			$counterResult->next();
			$resultData = $counterResult->getRow();
			$recordSet->setTotalRecordCount($resultData['totalCount']);
		}
		return $recordSet;
	}
	
	/**
	 * Gets a record set of related (referenced) records by performing a join tu a primary key
	 *
	 * @param string $foreignClassName
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords
	 * 
	 * @throws ARSchemaException
	 * @throws ARException
	 * @return ARSet
	 */
	public function getRelatedRecordSet($foreignClassName, ARSelectFilter $filter, $loadReferencedRecords = false) {
		
		$this->appendRelatedRecordJoinCond($foreignClassName, $filter);
		//return self::getRecordSet($foreignClassName, $filter, $loadReferencedRecords);
		return __invokeStaticMethod($foreignClassName, "getRecordSet", array($foreignClassName, $filter, $loadReferencedRecords));
	}
	
	private function appendRelatedRecordJoinCond($foreignClassName, ARSelectFilter $filter) {
		$foreignSchema = self::getSchemaInstance($foreignClassName);
		$callerClassName = get_class($this);
		$referenceFieldName = "";
		
		if($this->getID() === null) {
			throw new ARException("Related record set can be loaded only by a persisted object (so it must have a record ID)");
		}
		
		$connectingCond = null;
		foreach ($foreignSchema->getForeignKeyList() as $name => $field) {
			if ($field->getForeignClassName() == $callerClassName) {
				$connectingFieldName = $field->getName();
				//$connectingCond = new EqualsCond($foreignSchema->getName() . "." . $connectingFieldName, $this->getID());
				$connectingCond = new EqualsCond(new ARFieldHandle($foreignSchema->getName(), $connectingFieldName), $this->getID());
				break;
			}
		}
		if (empty($connectingFieldName)) {
			throw new ARSchemaException("Reference from " . $foreignClassName . " to " . $callerClassName . " is not defined in schema");
		}
		
		//$filter->appendCondition($foreignSchema->getName() . "." . $connectingFieldName. " = " . $this->getID());
		if($filter->isConditionSet()) {
			$mainCond = $filter->getCondition();
			$mainCond->addAND($connectingCond);
		} else {
			$filter->setCondition($connectingCond);
		}
	}
	
	public function getRelatedRecordSetArray($foreignClassName, ARSelectFilter $filter, $loadReferencedRecords = false) {
		$this->appendRelatedRecordJoinCond($foreignClassName, $filter);
		return self::getRecordSetArray($foreignClassName, $filter, $loadReferencedRecords);
	}
	
	/**
	 * Delete o set of records of this class by using a filter
	 *
	 * @param string $className
	 * @param ARDeleteFilter $filter
	 */
	public static function deleteRecordSet($className, ARDeleteFilter $filter) {
		$schema = self::getSchemaInstance($className);
		$db = self::getDBConnection();
		
		$deleteQuery = "DELETE FROM " . $schema->getName() . " " . $filter->createString();
		return $db->executeUpdate($deleteQuery);
	}
	
	/**
	 * Removes a persisted object (deletes from a database) by an uniqu identifier (record ID)
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * 
	 * @todo remove / replace self::enumerateID method call
	 */
	public static function deleteByID($className, $recordID) {
		
		$filter = new ARDeleteFilter();
		$schema = self::getSchemaInstance($className);
		$PKList = $schema->getPrimaryKeyList();
		$PKValueCond = null;
		if (!is_array($PKValueCond)) {
			$PKFieldName = $PKList[key($PKList)]->getName();
			$PKValueCond = new EqualsCond(new ARFieldHandle($className, $PKFieldName), $recordID);
		} else {
			foreach ($PKList as $PK) {
				$cond = new EqualsCond(new ARFieldHandle($className, $PK->getName()), $recordID[$PK->getName()]);
				if (empty($PKValueCond)) {
					$PKValueCond = $cond;
				} else {
					$PKValueCond->addAND($cond);
				}
			}
		}

		$filter->setCondition($PKValueCond);
		self::deleteRecordSet($className, $filter);
	}
	
	/**
	 * Updates a set of records by using ARUpdateFilter
	 *
	 * @param class $className
	 * @param ARUpdateFilter $filter
	 * @return unknown
	 */
	public static function updateRecordSet($className, ARUpdateFilter $filter) {
		$schema = self::getSchemaInstance($className);
		$db = self::getDBConnection();
		
		$updateQuery = "UPDATE " . $schema->getName() . " " . $filter->createString();
		return $db->executeUpdate($updateQuery);
	}
	
	/**
	 * Checks if an instance is being modified and needs to be saved
	 *
	 * @return bool
	 */
	public function isModified() {
		foreach ($this->data as $dataContainer) {
			if ($dataContainer->isModified()) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Checks if an instance maps to an existing database record.
	 *
	 * @return Returns true if such a record exists and false if it is a new record not being saved to a database
	 */
	public function isExistingRecord() {
		if ($this->hasID()) {
			$PKList = $this->schema->getPrimaryKeyList();
			if (count($PKList) > 1) {
				foreach ($PKList as $field) {
					if ($this->data[$field->getName()]->isModified()) {
						return false;
					}
				}
				return true;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Saves a record to a database 
	 *
	 * This method decides wheather to update or create new record by analysing its 
	 * information and shema
	 * 
	 * @todo make some optimization and code formatting
	 */
	public function save($forceOperation = 0) {
		
		if ($forceOperation) {
			if ($forceOperation == self::PERFORM_UPDATE) {
				if ($this->isModified()) {
					$this->setupDBConnection();
					$this->update();
				}
			} else {
				$this->setupDBConnection();
				$this->insert();
			}
			return;
		}
		
		if ($this->isExistingRecord()) {
			if($this->isModified()) {
				$this->setupDBConnection();
				$this->update();
			}
		} else {
			$this->setupDBConnection();
			$this->insert();
			if (count($this->schema->getPrimaryKeyList()) == 1) {
				$PKList = $this->schema->getPrimaryKeyList();
				$PKField = $PKList[key($PKList)];
				if ($PKField->getDataType() instanceof Numeric) {
					$IDG = $this->db->getIdGenerator();
					$this->setID($IDG->getId(), false);
				}
			}
		}
	}
	
	/**
	 * Updates an existing record by collecting modified field list
	 *
	 * @return int Rows affected
	 */
	protected function update() {

		$filter = new ARUpdateFilter();
		$updateCond = null;
		$PKList = $this->schema->getPrimaryKeyList();
		$className = get_class($this);
		
		foreach ($PKList as $PKField) {
			$recordID = "";
			if ($PKField instanceof ARForeignKey) {
				$recordObj = $this->data[$PKField->getName()]->get();
				$recordID = $recordObj->getID();
			} else {
				$recordID = $this->data[$PKField->getName()]->get();
			}
			//$cond = new EqualsCond($className . "." . $PKField->getName(), $recordID);
			$cond = new EqualsCond(new ARFieldHandle($className, $PKField->getName()), $recordID);
			$filter->mergeCondition($cond);
		}
		$updateQuery = "UPDATE " . $this->schema->getName() . " SET " . $this->enumerateModifiedFields() . " " . $filter->createString();
		
		self::getLogger()->logQuery($updateQuery);
		return $this->db->executeUpdate($updateQuery);
	}
	
	/**
	 * Creates a new persisted instance of activerecord (Inserts a new record to a database)
	 *
	 * @return int Rows affected
	 */
	protected function insert() {
		$insertQuery = "INSERT INTO " . $this->schema->getName() . " SET " . $this->enumerateModifiedFields();
		
		self::getLogger()->logQuery($insertQuery);
		return $this->db->executeUpdate($insertQuery);
	}
	
	/**
	 * Checks if record primary key value is set 
	 *
	 * @return bool
	 */
	public function hasID() {

		$PKFieldList = $this->schema->getPrimaryKeyList();
		foreach ($PKFieldList as $field) {
			if(!$this->data[$field->getName()]->hasValue()) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Creates a string of comma separated modified record fields in the folowing format: 
	 * "tableField = recordValue, field2=value2, ...". This string is used for INSERT and 
	 *  UPDATE queries
	 *
	 * @return string
	 */
	protected function enumerateModifiedFields() {
		
		$fieldListString = "";
		$fieldList = array();
		foreach ($this->data as $fieldName => $dataContainer) {
			//if (!($dataContainer->getField() instanceof ARPrimaryKeyField) && $dataContainer->isModified()) {
			if ($dataContainer->isModified()) {
				if ($dataContainer->getField() instanceof ARForeignKey) {
					$value =  "'" . $dataContainer->get()->getID() . "'";
				} else {
					$value = "'" . $dataContainer->get() . "'";
				}
				if ($dataContainer->isNull()) {
					$value = "NULL";
				}
				$fieldList[] = "`" . $dataContainer->getField()->getName() . "` = " . $value;
			}
		}
		return implode(", ", $fieldList);
	}
	
	/**
	 * Creates a comma separated string of primary key fields and values: 
	 * pk_field_name1 = value1, pk_field_name2 = value2, ...
	 *
	 * @param string $className
	 * @param array $recordID Record ID (some primary key value)
	 * @return 
	 * 
	 * @todo Smarter way to handle primary key field count missmatch (instead of ARException)
	 */
	public static function enumerateID($className, $recordID) {
		
		$schema = self::getSchemaInstance($className);
		if (!is_array($recordID)) {
			$PKList = $schema->getPrimaryKeyList();
			if(count($PKList) > 1) {
				throw new ARException("Primary key consists of multiple fields. Single value supplied!");
			}
			$fieldName = key($PKList);
			return $schema->getName() . "." . $fieldName . " = '" . $recordID . "'";
		} else {
			$fieldList = array();
			foreach ($recordID as $name => $value) {
				$fieldList[] = $schema->getName() . "." . $name . " = '" . $value . "'";
			}
			return implode(" AND ", $fieldList);
		}
	}
	
	/**
	 * Checks if such object is persisted in a database
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * @return bool
	 */
	public static function objectExists($className, $recordID) {

		$db = self::getDBConnection();
		$schema = self::getSchemaInstance($className);
		$selectString = "SELECT * FROM " . $schema->getName() . " WHERE " . self::enumerateID($className, $recordID);
		
		self::getLogger()->logQuery($selectString);
		$result = $db->executeQuery($selectString);
		if($result->getRecordCount() == 0) {
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * Sets a value of record field
	 *
	 * @param string $fieldName
	 * @param mixed $fieldValue
	 */
	public function setFieldValue($fieldName, $fieldValue) {
		$this->data[$fieldName]->set($fieldValue);
	}
	
	public function getFieldValue($fieldName) {
		return $this->data[$fieldName]->get();
	}

	/**
	 * Creates an array representing record data
	 *
	 * Array is created recursively: if this instance containes a reference to other 
	 * ActiveRecord instance (foreign key) than it also calls its toArray() method
	 * 
	 * @return array
	 */
	public function toArray($recursive = true) {
		$data = array();
		foreach ($this->data as $name => $value) {
			if ($value->getField() instanceof ARForeignKey) {
				if ($value->get() != null) {
					if ($recursive) {
						$data[$value->getField()->getForeignClassName()] = $value->get()->toArray();
					} else {
						$data[$value->getField()->getForeignClassName()] = $value->get()->getID();
					}
				}
			} else {
				$data[$name] = $value->get();
			}
		}
		return $data;
	}
	
	/**
	 * Gets a record set as array 
	 * 
	 * Gets a record set as array without creating complex object structure wich 
	 * allows to modify record on the fly.
	 * 
	 * Use this method only when you need to fetch and view data
	 *
	 * @param string $className
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords
	 * @return array
	 */
	public static function getRecordSetArray($className, ARSelectFilter $filter, $loadReferencedRecords = false) {

		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);
		
		$queryResultData = self::fetchDataFromDB($query);
		$resultDataArray = array();
		
		foreach ($queryResultData as $rowData) {
			$parsedRowData = self::prepareDataArray($className, $rowData, $loadReferencedRecords);
			$resultDataArray[] = array_merge($parsedRowData['recordData'], $parsedRowData['referenceData']);
		}
		
		return $resultDataArray;
	}
	
	/**
	 * Gets a record instance as array (loads record data)
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * @param bool $loadReferencedData
	 * 
	 * @todo implementation!
	 */
	public static function getInstanceArray($className, $recordID, $loadReferencedData = false) {
		throw new ARException("Not implemented!");
	}
	
	public static function getLogger() {
		if (empty(self::$logger)) {
			self::$logger = new Logger();
		}
		return self::$logger;
	}
	
	public function isLoaded() {
		return $this->isLoaded;
	}
	
	/**
	 * Abstract method for table schema definition
	 * 
	 * All derived classes must implement this method and perform the following steps:
	 * <li>Set table name</li>
	 * <li>Register at least one record field</li>
	 *
	 * @param string $className
	 * @see ARSchema
	 */
	abstract protected static function defineSchema($className = __CLASS__);
	
}

?>