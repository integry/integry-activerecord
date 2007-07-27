<?php

if (strpos(get_include_path(), dirname(__FILE__)) === false)
{
	set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__));
}

set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "schema" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "query" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "query" . DIRECTORY_SEPARATOR . "filter" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "schema" . DIRECTORY_SEPARATOR . "datatype" . DIRECTORY_SEPARATOR);

if (!function_exists("__autoload"))
{
	function __autoload($className)
	{
		@include_once $className.'.php';
	}
}

$dir = dirname(__file__) . '/';
include_once($dir . 'ARSet.php');
include_once($dir . 'ARValueMapper.php');
include_once($dir . 'schema/ARSchema.php');
include_once($dir . 'query/filter/ARFieldHandle.php');
include_once($dir . 'query/filter/ARExpressionHandle.php');
include_once($dir . 'query/filter/ARSelectFilter.php');
include_once($dir . 'query/filter/Condition.php');
include_once($dir . 'query/ARSelectQueryBuilder.php');

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
abstract class ActiveRecord implements Serializable
{

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

	public static $recordPool = null;

	/**
	 * Path to a Creole library
	 *
	 * @var string
	 */
	public static $creolePath = "";

	/**
	 * Indicates if the record is deleted from database
	 *
	 * @var boolean
	 */
	private $isDeleted = false;
	
	/**
	 * Database connection instance (refererences to self::$dbConnection)
	 *
	 * @see self::$dbConnection
	 * @var Creole
	 */
	private $db = null;

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
	protected $data = array();

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

	const RECURSIVE = true;
	const NON_RECURSIVE = false;

	const TRANSFORM_ARRAY = true;


	/**
	 * Is record data loaded from a database?
	 *
	 * @var bool
	 */
	protected $isLoaded = false;

	/**
	 * For emulating nested transactions
	 *
	 * @var bool
	 */
	public static $transactionLevel = 0;
	
	public static $logger = null;	
	
	/**
	 *	Cached object array data from the current toArray call stack
	 */
	protected static $toArrayData = array();
	
	protected $cachedId = null;
	
	/**
	 * ARSelectQueryBuilder object used in the last query
	 * 
	 * @var ARSelectQueryBuilder
	 */
    protected static $lastQuery = null;

	/**
	 * ARSelectFilter object used in the last query
	 */
	protected static $lastFilter = null;
	
	/**
	 * ActiveRecord constructor. Never use it directly
	 *
	 * @see self::getNewInstance()
	 * @see self::getInstanceByID()
	 */
	protected function __construct($data = array())
	{
		$this->schema = self::getSchemaInstance(get_class($this));
		$this->createDataAccessVariables($data);		
	}

	/**
	 * Creates data containers and instance variables to directly access ValueContainers
	 * (record field data)
	 *
	 * Record fields type of PKField has no direct access (instance variables are not created)
	 *
	 */
	private function createDataAccessVariables($data = array())
	{
		foreach($this->schema->getFieldList() as $name => $field)
		{			
            $this->data[$name] = new ARValueMapper($field, isset($data[$name]) ? $data[$name] : null);   
            if (!($field instanceof ARPrimaryKey))
			{
				$this->$name = $this->data[$name];
			}
        }
        
        foreach ($this->schema->getForeignKeyList() as $name => $field)
		{
			$referenceName = $field->getReferenceName();
			$foreignClassName = $field->getForeignClassName();
    
			if (!($this->data[$name]->get() instanceof ActiveRecord) && isset($data[$name]))
			{
				if (isset($data[$referenceName]))
			    {
			        foreach($data as $referecedTableName => $referencedData)
			        {
			            if (($referenceName != $referecedTableName) && $referencedData && !isset($data[$referenceName][$referecedTableName])) 
			            {
			                $data[$referenceName][$referecedTableName] = $referencedData;
			            }
			        }

					$this->data[$name]->set(self::getInstanceByID($foreignClassName, $data[$name], false, null, $data[$referenceName]), false);  			
				}
				else
				{
				    $this->data[$name]->set(self::getInstanceByID($foreignClassName, $data[$name], false, null), false); 
				}
			}
						
			// Making first letter lowercase
			$referenceName = strtolower(substr($referenceName, 0, 1)).substr($referenceName, 1);
			$this->$referenceName = $this->data[$name];				
		}
		
		if ($data)
		{
		  	$this->isLoaded = true;
		}
	}

	/**
	 * Creates or gets an already created Schema object for a given class $className
	 *
	 * @param string $className
	 * 
	 * @return ARSchema
	 */
	public static function getSchemaInstance($className)
	{
	    if (!isset(self::$schemaMap[$className]))
		{
			self::$schemaMap[$className] = new ARSchema();

            call_user_func(array($className, 'defineSchema'));

			if (!self::$schemaMap[$className]->isValid())
			{
				throw new ARException("Invalid schema (".$className.") definition! Make sure it has a name assigned and fields defined (record structure)");
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
	private function setupDBConnection()
	{
		$this->db = self::getDBConnection();
	}

	/**
	 * Return a database connection object
	 *
	 * @return Creole db object
	 */
	public static function getDBConnection()
	{
		if (!self::$dbConnection)
		{
			set_include_path(get_include_path().PATH_SEPARATOR.self::$creolePath);
			include_once("creole".DIRECTORY_SEPARATOR."Creole.php");

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
	public function setID($recordID, $markAsModified = true)
	{
		$PKList = $this->schema->getPrimaryKeyList();

		if (!is_array($recordID))
		{
			if (count($PKList) == 1)
			{
				$PKFieldName = key($PKList);
				if ($this->data[$PKFieldName]->get()instanceof ARForeignKey)
				{
					$instance = self::getInstanceByID($this->schema->getField($PKFieldName)->getForeignClassName(), $recordID);
					$this->data[$PKFieldName]->set($instance, $markAsModified);
				}
				else
				{
					$this->data[$PKFieldName]->set($recordID, $markAsModified);
				}

			}
			else
			{
				debug_print_backtrace();
			    throw new ARException("Primary key consists of more than one field (recordID parameter must be an associative array)");
			}
		}
		else
		{
			if (count($recordID) == count($PKList))
			{
				foreach($recordID as $name => $value)
				{
					if ($this->schema->fieldExists($name))
					{
						$instance = self::getInstanceByID($this->schema->getField($name)->getForeignClassName(), $value);
						$this->data[$name]->set($instance, $markAsModified);
					}
					else
					{
						throw new ARException("No such primary key field: ".$name." (schema: ".$this->schema->getName().")");
					}
				}
			}
			else
			{
				print_r($recordID);
				throw new ARException("Unknown situation (not implemented?)");
			}
		}
		
		$this->cachedId = null;
	}

	/**
	 * Returns a primary key value of record
	 *
	 * @param mixed $recordID
	 * @return mixed record id. If primary key consists of more than one field an array is returned
	 */
	public function getID()
	{
		if (!$this->cachedId)
		{
            $PKList = $this->schema->getPrimaryKeyList();

			$PK = array();
			foreach($PKList as $name => $field)
			{
				if ($field instanceof ARPrimaryForeignKeyField)
				{
					$PK[$name] = $this->data[$name]->get()->getID();
				}
				else
				{
					$PK[$name] = $this->data[$name]->get();
				}
			}

			if (count($PK) == 1)
			{
				$this->cachedId = $PK[key($PK)];
			}
			else
			{
				$this->cachedId = $PK;
			}
		}

		return $this->cachedId;
	}
	
	/**
	 * Creates a new instance of record
	 *
	 * Use this method when you are going to create a new persistent object. If you
	 * need to load an existing one call self::getInstanceByID()
	 *
	 * @see self::getInstanceByID()
	 * @param string $className
	 * @param array $data Field values (associative array)
	 * @return ActiveRecord
	 */
	public static function getNewInstance($className, $data = array())
	{	    
	    return new $className($data);
	}

	/**
	 * Sets a DSN (database connection configuration info)
	 *
	 * @param string $dsn
	 */
	public static function setDSN($dsn)
	{
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
	 * @param array $data	Record data array (may include referenced record data)
	 *
	 * @link http://en.wikipedia.org/wiki/Flyweight_pattern
	 *
	 * @return ActiveRecord
	 */
	public static function getInstanceByID($className, $recordID, $loadRecordData = false, $loadReferencedRecords = false, $data = array())
	{		    
		$instance = self::retrieveFromPool($className, $recordID);

        if ($instance == null)
		{
			$instance = self::getNewInstance($className, $data);
			$instance->setID($recordID, false);
			self::storeToPool($instance);
		}
		else if(!$instance->isLoaded() && !empty($data))
		{
			$instance->createDataAccessVariables($data);
		}

		if ($loadRecordData)
		{
			$instance->load($loadReferencedRecords);
		}

		return $instance;
	}

	/**
	 * Removes a ActiveRecord subclass instance from a record pool (needed for unit testing only)
	 *
	 * @param ActiveRecord $instance
	 */
	public static function removeFromPool(ActiveRecord $instance)
	{
		$hash = self::getRecordHash($instance->getID());
		$className = get_class($instance);
		$instance->markAsNotLoaded();
		unset(self::$recordPool[$className][$hash]);
	}
	
	/**
	 * This method should only be used for unit testing in tearDown method
	 *
	 */
	public static function removeClassFromPool($className)
	{
		unset(self::$recordPool[$className]);
	}
	
	/**
	 * This method should only be used for unit testing
	 *
	 */
	public static function clearPool()
	{
		self::$recordPool = array();
	}
	
	/**
	 * Stores ActiveRecord subclass instance in a record pool
	 *
	 * @param ActiveRecord $instance
	 */
	private static function storeToPool(ActiveRecord $instance)
	{		
//		var_dump(self::getRecordHash($instance->getID()));
        self::$recordPool[get_class($instance)][self::getRecordHash($instance->getID())] = $instance;
	}

	/**
	 * Retrieves ActiveRecord subclass instance from a record pool
	 *
	 * @param string $className
	 * @param mixed $recordID
	 * @return ActiveRecord Instance of requested object or null if object is not stored in a pool
	 */
	public static function retrieveFromPool($className, $recordID = null)
	{
		if(!is_null($recordID))
		{		    
		    $hash = self::getRecordHash($recordID);
			if (!empty(self::$recordPool[$className][$hash]))
			{
				return self::$recordPool[$className][$hash];
			}
		}
		else if (isset(self::$recordPool[$className]))
		{
			return self::$recordPool[$className];
		}
		
		return null;
		
	}

	/**
	 * Gets a unique string representinh concrete record
	 *
	 * @param mixed $recordID
	 * @return string
	 */
	private static function getRecordHash($recordID)
	{
		if (!is_array($recordID))
		{
			return $recordID;
		}
		else
		{
			ksort($recordID);
			$hashElements = array();
			foreach($recordID as $key => $value)
			{
				$hashElements[] = $value;
			}
			return implode("-", $hashElements);
		}
	}

	/**
	 * Creates a select query object for a table identified by an ActiveRecord class name
	 *
	 * @param string $className
	 * @param bool $loadReferencedRecords Join records on foreign keys?
	 * @return string
	 */
	public static function createSelectQuery($className, $loadReferencedRecords = false)
	{
		$schema = self::getSchemaInstance($className);
		$schemaName = $schema->getName();
		
        $query = new ARSelectQueryBuilder();
		$query->includeTable($schemaName);

		// Add main table fields to the select query
        foreach($schema->getFieldList() as $fieldName => $field)
		{
			$query->addField($fieldName, $schemaName);
		}

		if ($loadReferencedRecords)
		{
            $tables = is_array($loadReferencedRecords) ? array_flip($loadReferencedRecords) : $loadReferencedRecords;
			self::joinReferencedTables($schema, $query, $tables);		  
		}

		return $query;
	}
	
	protected static function joinReferencedTables(ARSchema $schema, ARSelectQueryBuilder $query, $tables = false)
	{
		$referenceList = $schema->getForeignKeyList();
		$schemaName = $schema->getName();

		foreach($referenceList as $name => $field)
		{
            $foreignClassName = $field->getForeignClassName();
			$tableAlias = $field->getReferenceName();
			$foreignSchema = self::getSchemaInstance($foreignClassName);
			$foreignTableName = $foreignSchema->getName();
                			
			if (($schema === $foreignSchema) || 
                (is_array($tables) && 
                    (!isset($tables[$foreignClassName])) || 
                    (isset($tables[$foreignClassName]) && !is_numeric($tables[$foreignClassName]) && $tables[$foreignClassName] != $tableAlias)
                ))
			{
				continue;
			}
			
			$joined = $query->joinTable($foreignTableName, $schemaName, $field->getForeignFieldName(), $name, $tableAlias);
			
			if ($joined)
			{
				foreach($foreignSchema->getFieldList() as $foreignFieldName => $foreignField)
				{
					$query->addField($foreignFieldName, $tableAlias, $tableAlias."_".$foreignFieldName);
				}
				
				self::getLogger()->logQuery('Joining ' . $foreignClassName . ' on ' . $schemaName); 

				self::joinReferencedTables($foreignSchema, $query, $tables);
			}
		}
	}

	/**
	 * Loads and sets persisted record data from a database
	 *
	 * @param bool $loadReferencedRecords
	 */
	public function load($loadReferencedRecords = false)
	{
		if ($this->isLoaded)
		{
			return ;
		}
		$query = self::createSelectQuery(get_class($this), $loadReferencedRecords);
		$this->loadData($loadReferencedRecords, $query);
		$this->isDeleted = false;
	}

	protected final function loadData($loadReferencedRecords, ARSelectQueryBuilder $query)
	{
		$className = get_class($this);
		$PKCond = null;
		foreach($this->schema->getPrimaryKeyList() as $name => $PK)
		{
            if ($PK instanceof ARForeignKey)
			{
				$PKValue = $this->data[$name]->get()->getID();
			}
			else
			{
				$PKValue = $this->data[$name]->get();
			}
			
			if ($PKCond == null)
			{
				$PKCond = new EqualsCond(new ARFieldHandle($className, $name), $PKValue);
			}
			else
			{
				$PKCond->addAND(new EqualsCond(new ARFieldHandle($className, $name), $PKValue));
			}
		}

		$query->getFilter()->mergeCondition($PKCond);
		$rowDataArray = self::fetchDataFromDB($query);

		if (empty($rowDataArray))
		{
			throw new ARNotFoundException($className, $this->getID());
		}
		if (count($rowDataArray) > 1)
		{
			throw new ARException("Unexpected behavior: got more than one record from a database while loading single instance data");
		}

        $parsedRowData = self::prepareDataArray($className, $this->schema, $rowDataArray[0], $loadReferencedRecords);
		
		$this->createDataAccessVariables($parsedRowData['recordData']);
		
		if (!empty($parsedRowData['miscData']))
		{
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
	protected static function extractRecordID(ARSchema $schema, $dataArray)
	{
		$PKList = $schema->getPrimaryKeyList();

		if (count($PKList) == 1)
		{
			return $dataArray[key($PKList)];	
		}
		else
		{
			$recordID = array();
			
			foreach($PKList as $name => $field)
			{
				$recordID[$name] = $dataArray[$name];
			}
			
			return $recordID;			
		}
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
    public static function prepareDataArray($className, ARSchema $schema, $dataArray, $loadReferencedRecords = false, $transformArray = false)
	{
		$referenceListData = array();
		$recordData = array();
		$miscData = array();
        $recordKeys = array();

        foreach($schema->getArrayFieldList() as $name => $field)
		{
			$dataArray[$name] = unserialize($dataArray[$name]);
		}

		$recordData = array_intersect_key($dataArray, $schema->getFieldList());
		$dataArray = array_diff_key($dataArray, $recordData);

		if ($transformArray)
		{
		  	$recordData = call_user_func_array(array($className, 'transformArray'), array($recordData, $schema));
		}					

		if ($loadReferencedRecords)
		{
			$schemas = $schema->getReferencedSchemas();
			
			// remove schemas that were not loaded with this query
			if (is_array($loadReferencedRecords))
			{
				$loadReferencedRecords = array_flip($loadReferencedRecords);
				$filteredSchemas = array();
				foreach($loadReferencedRecords as $tableName => $tableAlias)
				{   
				    if (is_numeric($tableAlias))
				    {
				        $filteredSchemas[$tableName] = $schemas[$tableName][0];
				    }
				    else
				    {
				        foreach($schemas[$tableAlias] as $key => $unfilteredSchema)
				        {
                            if ($unfilteredSchema->getName() == $tableName)
				            {
				                $filteredSchemas[$tableAlias] = $unfilteredSchema;
				            }
				        }
				    }
				}
				$schemas = $filteredSchemas;
			}
			else 
            {
                foreach ($schemas as $referenceName => $foreignSchema) 
                {
                    $schemas[$referenceName] = $foreignSchema[0];    
                }
            }
			
            $referenceListData = array_fill_keys(array_keys($schemas), array());
						
			foreach ($schemas as $referenceName => $foreignSchema)
			{
				$foreignSchemaName = $foreignSchema->getName();

				$fieldNames = array_keys($foreignSchema->getFieldList());
                $referenceKeys = array();
                foreach($fieldNames as $fieldName)
				{					
                    $referenceKeys[$fieldName] = $referenceName . '_' . $fieldName;
                }

				foreach ($foreignSchema->getArrayFieldList() as $fieldName => $field)
				{
					$dataArray[$referenceKeys[$fieldName]] = unserialize($dataArray[$referenceKeys[$fieldName]]);
                }
				
                $referenceListData[$referenceName] = array_combine($fieldNames, array_intersect_key($dataArray, array_flip($referenceKeys)));
                                
                $recordKeys = array_merge($recordKeys, array_values($referenceKeys));
				
				foreach ($foreignSchema->getForeignKeyList() as $fieldName => $field)
				{
					$deeperForeignSchemaName = $field->getForeignTableName();
					if ($foreignSchemaName != $deeperForeignSchemaName)
					{
						$referenceListData[$referenceName][$deeperForeignSchemaName] =& $referenceListData[$deeperForeignSchemaName];						  
					}                    
                }
				
				if ($transformArray)
				{
				  	$referenceListData[$referenceName] = call_user_func_array(array($foreignSchemaName, 'transformArray'), array($referenceListData[$referenceName], $foreignSchema));
				}		

				$recordData[$referenceName] = $referenceListData[$referenceName];
			}
		}
	    
        return array("recordData" => $recordData, "referenceData" => $referenceListData, "miscData" => array_diff_key($dataArray, array_flip($recordKeys)));
	}

	protected function miscRecordDataHandler($miscRecordDataArray)
	{
		echo '<pre>';
		print_r($miscRecordDataArray);
		echo '</pre>';
		throw new ARException("miscRecordDataHandler is not implemented");
	}

	/**
	 * Loads a set of active record instances (persisted object list) by using a filter
	 *
	 * @param string $className
	 * @param ARSelectFilter $filter
	 * @param bool $loadReferencedRecords
	 *
	 * @todo Smarter way to merge filters (from a query object and the one that is supplied as parameter)
	 *
	 * @return ARSet
	 */
	public static function getRecordSet($className, ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);
		
		self::$lastQuery = $query;
        return self::createRecordSet($className, $query, $loadReferencedRecords);
	}

	public static function fetchDataFromDB(ARSelectQueryBuilder $query)
	{
		$db = self::getDBConnection();
		$queryStr = $query->createString();
		self::getLogger()->logQuery($queryStr);
		$resultSet = $db->executeQuery($queryStr);
		self::getLogger()->logQueryExecutionTime();		
		$dataArray = array();
		while ($resultSet->next())
		{
			$dataArray[] = $resultSet->getRow();
		}
		return $dataArray;
	}

	public static function getDataBySQL($sqlSelectQuery)
	{
		$db = self::getDBConnection();
		self::getLogger()->logQuery($sqlSelectQuery);
		$resultSet = $db->executeQuery($sqlSelectQuery);
		$dataArray = array();
		while ($resultSet->next())
		{
			$dataArray[] = $resultSet->getRow();
		}
		return $dataArray;
	}

	public static function executeUpdate($sql)
	{
		return self::getDBConnection()->executeQuery($sql);		
	}

	public static function getRecordSetByQuery($className, ARSelectQueryBuilder $query)
	{
		return self::createRecordSet($className, $query);
	}

	/**
	 * Loads a set of active record instances (persisted object list) by using a query
	 *
	 * @param string $className
	 * @param ARSelectQueryBuilder $query
	 * @param bool $loadReferencedRecords
	 *
	 * @return ARSet
	 */
	protected final static function createRecordSet($className, ARSelectQueryBuilder $query, $loadReferencedRecords = false)
	{
		$schema = self::getSchemaInstance($className);

		$queryResultData = self::fetchDataFromDB($query);
		$recordSet = new ARSet($query->getFilter());
		$schema = self::getSchemaInstance($className);
        foreach($queryResultData as $rowData)
		{
			$parsedRowData = self::prepareDataArray($className, $schema, $rowData, $loadReferencedRecords);
			$recordID = self::extractRecordID($schema, $rowData);
			$instance = self::getInstanceByID($className, $recordID, null, null, $parsedRowData['recordData']);
			$recordSet->add($instance);
		    
			if (!empty($parsedRowData['miscData']))
			{
				$instance->miscRecordDataHandler($parsedRowData['miscData']);
			}
		}

		$filter = $query->getFilter();
		if ($filter->getLimit() >= $recordSet->size() || $filter->getOffset() > 0)
		{

			$db = self::getDBConnection();
			$counterFilter = clone $filter;
			$counterFilter->removeFieldList();
			$counterFilter->setLimit(0, 0);

			$query->removeFieldList();
			$query->addField("COUNT(*)", null, "totalCount");
			$query->setFilter($counterFilter);

			$counterQuery = $query->createString();

			self::getLogger()->logQuery($counterQuery);
			$counterResult = $db->executeQuery($counterQuery);
			$counterResult->next();

			$resultData = $counterResult->getRow();
			$recordSet->setTotalRecordCount($resultData['totalCount']);
		}
		
		return $recordSet;
	}

	public static function getRecordCount($className, ARSelectFilter $filter)
	{
		$db = self::getDBConnection();
		$counterFilter = clone $filter;
		$counterFilter->removeFieldList();
		$counterFilter->setLimit(0, 0);

		$query = new ARSelectQueryBuilder();
		$query->removeFieldList();
		$query->addField("COUNT(*)", null, "totalCount");
		$query->includeTable($className);
		$query->setFilter($counterFilter);

		$counterQuery = $query->createString();

		self::getLogger()->logQuery($counterQuery);
		$counterResult = $db->executeQuery($counterQuery);
		$counterResult->next();

		$resultData = $counterResult->getRow();
		return $resultData['totalCount'];		
	}

    public static function getRecordCountByQuery(ARSelectQueryBuilder $query)
    {
        $query = clone $query;
		$query->removeFieldList();
		$query->addField("COUNT(*)", null, "totalCount");

		$counterQuery = $query->createString();

		self::getLogger()->logQuery($counterQuery);

		$db = self::getDBConnection();
		$counterResult = $db->executeQuery($counterQuery);
		$counterResult->next();

		$resultData = $counterResult->getRow();
		return $resultData['totalCount'];		
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
	public function getRelatedRecordSet($foreignClassName, ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		$this->appendRelatedRecordJoinCond($foreignClassName, $filter);

		return self::getRecordSet($foreignClassName, $filter, $loadReferencedRecords);
	}

	private function appendRelatedRecordJoinCond($foreignClassName, ARSelectFilter $filter)
	{
		$foreignSchema = self::getSchemaInstance($foreignClassName);
		$callerClassName = get_class($this);
		$referenceFieldName = "";

		$id = $this->getID();
        if (is_null($id))
		{
			throw new ARException("Related record set can be loaded only by a persisted object (so it must have a record ID)");
		}

		foreach($foreignSchema->getForeignKeyList() as $name => $field)
		{
			if ($field->getForeignClassName() == $callerClassName)
			{
				$filter->mergeCondition(new EqualsCond(new ARFieldHandle($foreignClassName, $name), $id));
				return;
			}
		}
		
		throw new ARSchemaException("Reference from ".$foreignClassName." to ".$callerClassName." is not defined in schema");
	}

	public function getRelatedRecordSetArray($foreignClassName, ARSelectFilter $filter, $loadReferencedRecords = false)
	{
		$this->appendRelatedRecordJoinCond($foreignClassName, $filter);
		return self::getRecordSetArray($foreignClassName, $filter, $loadReferencedRecords);
	}

	/**
	 * Delete o set of records of this class by using a filter
	 *
	 * @param string $className
	 * @param ARDeleteFilter $filter
	 */
	public static function deleteRecordSet($className, ARDeleteFilter $filter)
	{
		$schema = self::getSchemaInstance($className);
		$db = self::getDBConnection();

		$deleteQuery = "DELETE FROM ".$schema->getName()." ".$filter->createString() ."\n";
		if(isset(self::$recordPool[$className]))
		{
			foreach(self::$recordPool[$className] as $record)
			{
				$record->markAsNotLoaded();
				$record->markAsDeleted();
			}
		}
		
		self::getLogger()->logQuery($deleteQuery);
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
	public static function deleteByID($className, $recordID)
	{
		$filter = new ARDeleteFilter();
		$schema = self::getSchemaInstance($className);
		$PKList = $schema->getPrimaryKeyList();
		$PKValueCond = null;
		if (!is_array($recordID))
		{
			$PKFieldName = $PKList[key($PKList)]->getName();
			$PKValueCond = new EqualsCond(new ARFieldHandle($className, $PKFieldName), $recordID);
		}
		else
		{
			foreach($PKList as $PK)
			{
				$cond = new EqualsCond(new ARFieldHandle($className, $PK->getName()), $recordID[$PK->getName()]);
				if (empty($PKValueCond))
				{
					$PKValueCond = $cond;
				}
				else
				{
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
	public static function updateRecordSet($className, ARUpdateFilter $filter)
	{
		$schema = self::getSchemaInstance($className);
		$db = self::getDBConnection();

		$updateQuery = "UPDATE ".$schema->getName()." ".$filter->createString();
		self::getLogger()->logQuery($updateQuery);
		return $db->executeUpdate($updateQuery);
	}

	/**
	 * Checks if an instance is being modified and needs to be saved
	 *
	 * @return bool
	 */
	public function isModified()
	{
		foreach($this->data as $dataContainer)
		{
			if ($dataContainer->isModified())
			{
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
	public function isExistingRecord()
	{
		if ($this->hasID())
		{
			$PKList = $this->schema->getPrimaryKeyList();
			if (count($PKList) > 1)
			{
				// at least one primary key field must be unmodified
				foreach($PKList as $field)
				{
					if (!$this->data[$field->getName()]->isModified())
					{
						return true;
					}
				}
				return false;
			}
			else
			{
				return true;
			}
		}
		else
		{
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
	public function save($forceOperation = 0)
	{
		$this->setupDBConnection();
		if ($forceOperation)		
		{
		  	$action = ($forceOperation == self::PERFORM_UPDATE) ? self::PERFORM_UPDATE : self::PERFORM_INSERT;
		}
		else
		{
			if ($this->isExistingRecord())
			{
				if ($this->isModified())
				{
					$action = self::PERFORM_UPDATE;	  
				}	
				else
				{
				  	return false;
				}			  	
			}
			else
			{
				$action = self::PERFORM_INSERT;	  			  
			}				  
		}

				
		if (self::PERFORM_UPDATE == $action)
		{
			$this->update();
		}
		else
		{
			$this->insert();
		}
		
		$this->resetModifiedStatus();
		$this->markAsLoaded();
	}

	public function resetModifiedStatus()
	{
		foreach($this->data as $dataContainer)
		{
			$dataContainer->resetModifiedStatus();
		} 	
	}

	/**
	 * Updates an existing record by collecting modified field list
	 *
	 * @return int Rows affected
	 */
	protected function update()
	{
		$filter = new ARUpdateFilter();
		$PKList = $this->schema->getPrimaryKeyList();
		$className = get_class($this);

		foreach($PKList as $PKField)
		{
			$recordID = "";
			if ($PKField instanceof ARForeignKey)
			{
				//$recordID = $this->data[$PKField->getName()]->get()->getID();
				$recordID = $this->data[$PKField->getName()]->getInitialID();
			}
			else
			{
				$recordID = $this->data[$PKField->getName()]->get();
			}
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
	protected function insert()
	{
		$insertQuery = "INSERT INTO ".$this->schema->getName()." SET ".$this->enumerateModifiedFields();

		self::getLogger()->logQuery($insertQuery);
		$result = $this->db->executeUpdate($insertQuery);
		
		// get inserted record ID
		if (count($this->schema->getPrimaryKeyList()) == 1)
		{
			$PKList = $this->schema->getPrimaryKeyList();
			$PKField = $PKList[key($PKList)];
			if ($PKField->getDataType() instanceof ARInteger )
			{
			    $IDG = $this->db->getIdGenerator();
				$this->setID($IDG->getId(), false);
			}
		}	

		self::storeToPool($this);
		
		return $result;		
	}

	/**
	 * Deletes an existing record
	 */
	public function delete()
	{
		if ($this->getID())
		{
			self::deleteByID(get_class($this), $this->getID());			
		}

		$this->markAsNotLoaded();
		$this->cachedId = false;
		$this->markAsDeleted();
				
		return true;
	}

	/**
	 * Checks if record primary key value is set
	 *
	 * @return bool
	 */
	public function hasID()
	{
		if ($this->isDeleted) 
		{
			return false;	
		}

		$PKFieldList = $this->schema->getPrimaryKeyList();
				
		foreach($PKFieldList as $field)
		{
			if (!$this->data[$field->getName()]->hasValue())
			{
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
	protected function enumerateModifiedFields()
	{
		$fieldListString = "";
		$fieldList = array();
		foreach($this->data as $fieldName => $dataContainer)
		{
			//if (!($dataContainer->getField() instanceof ARPrimaryKeyField) && $dataContainer->isModified()) {
			if ($dataContainer->isModified())
			{
				if ($dataContainer->getField() instanceof ARForeignKey)
				{
					if (!$dataContainer->isNull() && !is_null($dataContainer->get()))
					{
                        $value = "'".$dataContainer->get()->getID()."'";
					}
				}
				else
				{
					$value = $dataContainer->get();
					if ($dataContainer->getField()->getDataType() instanceof ARArray)
					{
						$value = serialize($value);
						$value = str_replace("\\", "\\\\", $value);
						$value = "'" . str_replace("'", "\'", $value) . "'";
					}
					else if ($dataContainer->getField()->getDataType() instanceof ARNumeric && $value)
					{
						// no changes for numeric fields
					}
					else if ($value instanceof ARExpressionHandle)
					{
						// no changes for raw database expression
					}
					else
					{
						$value = str_replace("\\", "\\\\", $value);
						$value = "'" . str_replace("'", "\'", $value) . "'";
					}
				}
				
                if ($dataContainer->isNull())
				{
					$value = "NULL";
				}
				
                $fieldList[] = "`".$dataContainer->getField()->getName()."` = ".$value;
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
	public static function enumerateID($className, $recordID)
	{
		$schema = self::getSchemaInstance($className);
		if (!is_array($recordID))
		{
			$PKList = $schema->getPrimaryKeyList();
			if (count($PKList) > 1)
			{
				throw new ARException("Primary key consists of multiple fields. Single value supplied!");
			}
			$fieldName = key($PKList);
			return $schema->getName().".".$fieldName." = '".$recordID."'";
		}
		else
		{
			$fieldList = array();
			foreach($recordID as $name => $value)
			{
				$fieldList[] = $schema->getName().".".$name." = '".$value."'";
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
	public static function objectExists($className, $recordID)
	{
		$db = self::getDBConnection();
		$schema = self::getSchemaInstance($className);

		$selectString = "SELECT COUNT(*) AS `count` FROM ".$schema->getName()." WHERE ".self::enumerateID($className, $recordID);
		self::getLogger()->logQuery($selectString);
		
		$result = self::getDataBySQL($selectString);
		
		return $result[0]['count'] > 0;
	}

	/**
	 * Sets a value of record field
	 *
	 * @param string $fieldName
	 * @param mixed $fieldValue
	 */
	public function setFieldValue($fieldName, $fieldValue)
	{
		$this->data[$fieldName]->set($fieldValue);
	}

	public function getFieldValue($fieldName)
	{
		return $this->data[$fieldName]->get();
	}

	public function getField($fieldName)
	{
		return $this->data[$fieldName];
	}

	/**
	 * Creates an array representing record data
	 *
	 * Array is created recursively: if this instance containes a reference to other
	 * ActiveRecord instance (foreign key) than it also calls its toArray() method
	 *
	 * @param bool $force Force to recreate array
	 * 
	 * @return array
	 */
	public function toArray($force = false)
	{    
	    // create a unique identifier of the current record
		$className = get_class($this);
	    $currentIdentifier = $this->getRecordIdentifier($this);

		// check if this record has been processed already
		if (!$force && isset(self::$toArrayData[$currentIdentifier]))
	   	{
			return self::$toArrayData[$currentIdentifier];
		}
	    
		$data = array(); 
		
		self::$toArrayData[$currentIdentifier] =& $data;
		
		foreach($this->data as $name => $value)
		{
		    $fieldValue = $value->get();
		    
            if ($value->getField() instanceof ARForeignKey)
			{
				if ($fieldValue != null)
				{
				    $varName = $value->getField()->getForeignClassName();
				    if (substr($name, -2) == 'ID') 
					{
						$varName = ucfirst(substr($name, 0, -2));
		    		}	    

                    $foreignIdentifier = $this->getRecordIdentifier($fieldValue);
                    if (!$force && isset(self::$toArrayData[$foreignIdentifier]))
                    {
                        $data[$varName] =& self::$toArrayData[$foreignIdentifier];
                    }
                    else
                    {
                        $data[$varName] =& $fieldValue->toArray($force);
                    }
				}
			}
			else
			{
			    if ($fieldValue instanceof ARSerializableDateTime)
	    		{
                    $data[$name] = $fieldValue->format('Y-m-d H:i:s');
	    		}
	    		else
	    		{
			        $data[$name] = $fieldValue;
	    		}
			}
		}
	
		$data = call_user_func_array(array($className, 'transformArray'), array($data, $this->schema));
		
		if (!$this->isLoaded())
		{
			unset(self::$toArrayData[$currentIdentifier]);
		}		
		
		return $data;
	}
	
	private function getRecordIdentifier(ActiveRecord $record)
	{
	    return get_class($record) . '-' . self::getRecordHash($record->getID());
    }
    
    protected function setArrayData($array)
    {
        self::$toArrayData[$this->getRecordIdentifier($this)] = $array;
    }
	
	/**
	 * Creates an array representing record data (without referenced records)
	 *
	 * The returned array will NOT contain referenced data, only referenced record ID's
	 * Use toArray() if you need to retrieve the whole data structure.
	 *
	 * @return array
	 */
	public function toFlatArray()
	{
		$data = array(); 
		
		foreach($this->data as $name => $value)
		{
			if ($value->getField() instanceof ARForeignKey)
			{
				if ($value->get() != null)
				{
					$data[$value->getField()->getForeignClassName()] = $value->get()->getID();
				}
			}
			else
			{
				$data[$name] = $value->get();
			}
		}
		
		return call_user_func_array(array(get_class($this), 'transformArray'), array($data, $this->schema));
	}

	/**
	 *	Perform model specific array transformation
	 */
	protected static function transformArray($array, ARSchema $schema)
	{
		return $array;  	
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
	public static function getRecordSetArray($className, ARSelectFilter $filter, $loadReferencedRecords = false, &$getRecordCount = null)
	{
		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);

		self::$lastQuery = $query;

		$queryResultData = self::fetchDataFromDB($query);
		$resultDataArray = array();

		$schema = self::getSchemaInstance($className);
        foreach($queryResultData as $rowData)
		{
			$parsedRowData = self::prepareDataArray($className, $schema, $rowData, $loadReferencedRecords, self::TRANSFORM_ARRAY);
			$resultDataArray[] = array_merge($parsedRowData['recordData'], $parsedRowData['referenceData']);
		}
    
        if (!is_null($getRecordCount))
        {
            $getRecordCount = self::getRecordCountByQuery($query);    
        }
		
        return $resultDataArray;
	}

	/**
	 * Gets record instances in array (loads record data)
	 *
	 * Useful when you know the ID's for the records you need to retrieve, but do not want to make
	 * extra queries, because these instances (or some of them) may already be available in pool
	 *
	 * @param string $className
	 * @param array $recordIDs
	 * @param bool $loadReferencedData
	 * @return array
	 */
	public static function getInstanceArray($className, $recordIDs, $loadReferencedData = false)
	{
		if (!is_array($recordIDs))
		{
		  	throw new Exception('ActiveRecord::getInstanceArray expects an array of record IDs!');
		}
		
		if (count($recordIDs) == 0)
		{
			return array();
		}

		$missingInstances = array();
		$ret = array();
		
		foreach ($recordIDs as $id)
		{
			$instance = self::retrieveFromPool($className, $id);
			
			if (null == $instance || !$instance->isLoaded())
			{
				$missingInstances[] = $id;
			}
			else
			{
				$ret[$id] = $instance;
			}		  
		}
		
		// get missing instances
		if ($missingInstances)
		{
			$filter = new ARSelectFilter();
			$cond = new INCond(new ARFieldHandle($className, 'ID'), $missingInstances);
			$filter->setCondition($cond);
			$set = self::getRecordSet($className, $filter, $loadReferencedData);
			
			foreach ($set as $instance)
			{
			  	$ret[$instance->getID()] = $instance;
			}		  
		}
		
		return $ret;
	}

	public static function getLogger()
	{
        if (empty(self::$logger))
		{
			include_once dirname(__file__) . '/ARLogger.php';
            self::$logger = new ARLogger();
		}
		return self::$logger;
	}
	
	/**
	 * @return ARSelectQueryBuilder
	 */
	public static function getLastQuery()
	{
        return self::$lastQuery;
    }

	/**
	 * Check if instance data is loaded
	 *
	 * @return boolean
	 */
	public function isLoaded()
	{
		return $this->isLoaded;
	}

	/**
	 * Change record status to loaded
	 *
	 */
	public function markAsLoaded()
	{
        $this->isLoaded = true;
	}
	
	/**
	 * Change record status to not loaded
	 */
	public function markAsNotLoaded()
	{
        $this->isLoaded = false;
	}
	
	/**
	 * Actualy unload current instance and once more  it loadfrom database
	 *
	 */
	public function reload($loadReferencedRecords = false)
	{
	    $this->markAsNotLoaded();
	    $this->load($loadReferencedRecords);
	}
	
	/**
	 * Mark as deleted record
	 */
	public function markAsDeleted()
	{
		$this->isDeleted = true;
	}
	
	public function isDeleted()
	{
		return $this->isDeleted;
	}

	/**
	 * Begins a transaction
	 *
	 */
	public static function beginTransaction()
	{		
		// only begin the transaction once
		self::getLogger()->logAction("BEGIN transaction " . ((int)self::$transactionLevel + 1));
		self::$transactionLevel++;
		if (1 == self::$transactionLevel)
		{
			$db = self::getDBConnection();
			$db->setAutoCommit(false);
		}
	}

	/**
	 * Commits a transaction
	 *
	 */
	public static function commit()
	{
		self::$transactionLevel--;
		self::getLogger()->logAction("COMMIT transaction");
		if (0 == self::$transactionLevel)
		{
			$db = self::getDBConnection();
			$db->commit();
		}
	}

	/**
	 * Rollbacks a transaction
	 *
	 */
	public static function rollback()
	{
		self::$transactionLevel = 0;
		self::getLogger()->logAction("ROLLBACK transaction");
		$db = self::getDBConnection();
		$db->rollback();
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
	protected static function defineSchema($className = __CLASS__)
	{
		throw new Exception('ActiveRecord::defineSchema must be implemented');  	
	}
	
    private function getVars()
    {
        return get_object_vars($this);
    }
	
	public function serialize($skippedRelations = array(), $properties = array())
	{
        if (!is_array($skippedRelations))
        {
            $skippedRelations = array();
        }
        $skippedRelations = array_flip($skippedRelations);
        
        $serialized = array('data' => array());
        
        // serialize data variables
        foreach ($this->data as $key => $value)
        {
            if (isset($skippedRelations[$key]))
            {
                $value = $value->get();
                if (!$value || !$value->getID())
                {
                    continue;
                }
                
                $value = new ARSerializedReference($value);
            }

            $serialized['data'][$key] = $value;
        }
        
        // serialize custom variables
        $properties[] = 'isLoaded';
        foreach ($properties as $key)
        {
            $serialized[$key] = $this->$key;   
        }

        $s = serialize($serialized);

//        echo 'Ending '.get_class($this) . "\n"; flush();        
        
        return $s;
    }
    
    public function unserialize($serialized)
    {
        $this->schema = self::getSchemaInstance(get_class($this));
        
        $array = unserialize($serialized);
                
        foreach ($this->schema->getForeignKeyList() as $field)
        {
            $fieldName = $field->getName();
            
            if (!isset($array['data'][$fieldName]))
            {
                continue;
            }
            
            $referenced = $array['data'][$fieldName];

            if (is_object($referenced) && ($referenced instanceof ARSerializedReference))
            {
                $array['data'][$fieldName] = $referenced->restoreInstance();
            }
        }

        $data = array();
        foreach ($array['data'] as $key => $valueMapper)
        {
            $data[$key] = ($valueMapper instanceof ARValueMapper) ? $valueMapper->get() : $valueMapper;
        }

        $this->createDataAccessVariables($data);
        unset($array['data']);
        
        foreach ($array as $key => $value)
        {
            $this->$key = $value;
        }        
        
        if ($this->isLoaded())
        {
            self::storeToPool($this);            
        }
    }
    
    public function __clone()
	{
		foreach ($this->data as $key => $valueMapper)
		{
			$this->data[$key] = clone $valueMapper;
		}
	}
	
	private function __get($name)
	{
		switch ($name)
	  	{
		    case 'db':
		    	$this->db = self::getDBConnection();
				return $this->db;
		    break;

			default:
		    break;
		}
	}	
}
?>