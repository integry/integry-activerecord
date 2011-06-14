<?php

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "schema" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "query" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "query" . DIRECTORY_SEPARATOR . "filter" . DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR.dirname(__FILE__) . DIRECTORY_SEPARATOR . "schema" . DIRECTORY_SEPARATOR . "datatype" . DIRECTORY_SEPARATOR);

if (!function_exists('array_fill_keys'))
{
	function array_fill_keys($array, $values)
	{
		if (is_array($array))
		{
			foreach($array as $key => $value)
			{
				$arraydisplay[$array[$key]] = $values;
			}
		}

		return $arraydisplay;
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
include_once($dir . 'ARShortHand.php');

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
 * @author Integry Systems
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

	protected $customSerializeData = array();

	protected $cachedId = null;

	private $isDestructing = false;

	/**
	 * ActiveRecord constructor. Never use it directly
	 *
	 * @see self::getNewInstance()
	 * @see self::getInstanceByID()
	 */
	protected function __construct($data = array(), $recordID = null)
	{
		$this->schema = self::getSchemaInstance(get_class($this));
		$this->createDataAccessVariables($data, $recordID);
	}

	/**
	 * Creates data containers and instance variables to directly access ValueContainers
	 * (record field data)
	 *
	 * Record fields type of PKField has no direct access (instance variables are not created)
	 *
	 */
	private function createDataAccessVariables($data = array(), $recordID = null)
	{
		foreach($this->schema->getFieldList() as $name => $field)
		{
			$this->data[$name] = new ARValueMapper($field, isset($data[$name]) ? $data[$name] : null);
			if (!($field instanceof ARPrimaryKey))
			{
				$this->$name = $this->data[$name];
			}
		}

		if ($recordID)
		{
			$this->setID($recordID, false);
			$this->storeToPool();
		}

		if ($data)
		{
			$this->isLoaded = true;
		}

		$this->createReferencedRecords($data, true);
	}

	private function createReferencedRecords($data, $initialState = true)
	{
		foreach ($this->schema->getForeignKeyList() as $name => $field)
		{
			$referenceName = $field->getReferenceName();
			$foreignClassName = $field->getForeignClassName();

			if ((!($this->data[$name]->get() instanceof ActiveRecord) || !$this->data[$name]->get()->isLoaded()) && isset($data[$name]))
			{
				if (!isset($data[$referenceName]))
				{
					foreach (array($referenceName, array_pop(explode('_', $referenceName))) as $referenceName)
					{
						if (isset($data[$referenceName]))
						{
							break;
						}
					}
				}

				if (isset($data[$referenceName]))
				{
					/*
					foreach($data as $referecedTableName => $referencedData)
					{
						if (($referenceName != $referecedTableName) && $referencedData && !isset($data[$referenceName][$referecedTableName]))
						{
							$data[$referenceName][$referecedTableName] = $referencedData;
						}
					}
					*/

					if (!self::extractRecordID(self::getSchemaInstance($foreignClassName), $data[$referenceName]))
					{
						$data[$referenceName] = null;
					}

					$this->data[$name]->set(self::getInstanceByID($foreignClassName, $data[$name], false, null, $data[$referenceName]), false);
				}
				else
				{
					$this->data[$name]->set(self::getInstanceByID($foreignClassName, $data[$name], false, null), false);
				}
			}

			if ($initialState)
			{
				// Making first letter lowercase
				$referenceName = $field->getReferenceFieldName();
				$referenceName = strtolower(substr($referenceName, 0, 1)).substr($referenceName, 1);
				$this->$referenceName = $this->data[$name];
			}
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
		static $cache;

		if (!isset(self::$schemaMap[$className]))
		{
			/********
			+++ UGLY UGLY UGLY +++ :(
			@todo: add generic schema caching interface
			*********/
/*
			if (!$cache)
			{
				$cache = ClassLoader::getRealPath('cache.schema.');
				if (!file_exists($cache))
				{
					mkdir($cache, 0777);
					chmod($cache, 0777);
				}
			}

			$cacheFile = $cache . $className . '.php';
			if (file_exists($cacheFile))
			{
				self::$schemaMap[$className] = include $cacheFile;
				return self::$schemaMap[$className];
			}
*/
			self::$schemaMap[$className] = new ARSchema();

			call_user_func(array($className, 'defineSchema'));

			if (!self::$schemaMap[$className]->isValid())
			{
				throw new ARException("Invalid schema (".$className.") definition! Make sure it has a name assigned and fields defined (record structure)");
			}

			/*file_put_contents($cacheFile, '<?php return unserialize(' . var_export(serialize(self::$schemaMap[$className]), true) . '); ?>');*/
			//chmod($cacheFile, 0777);
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
			include_once("creole".DIRECTORY_SEPARATOR."Creole.php");

			self::getLogger()->logQuery("Creating a database connection");
			self::$dbConnection = Creole::getConnection(self::$dsn);
			self::getLogger()->logQueryExecutionTime();

			self::$dbConnection->executeUpdate("SET NAMES 'utf8'");
			self::$dbConnection->executeUpdate("SET @@session.sql_mode=''");
		}
		return self::$dbConnection;
	}

	public static function resetDBConnection()
	{
		self::$dbConnection = null;
	}

	public function resetID()
	{
		$PKList = $this->schema->getPrimaryKeyList();

		foreach ($PKList as $fieldName => $field)
		{
			if (!($field instanceof ARForeignKey))
			{
				$this->data[$fieldName]->setNull();
			}
		}
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
				//if ($this->data[$PKFieldName]->get() instanceof ARForeignKey)
				if ($PKList[$PKFieldName] instanceof ARForeignKey)
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
					if (!$this->data[$name]->get())
					{
						return false;
					}

					$PK[$name] = $this->data[$name]->get()->getID();
				}
				else
				{
					$PK[$name] = $this->data[$name]->get();
				}
			}

			if (count($PK) == 1)
			{
				$this->cachedId = array_shift($PK);
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
	public static function getNewInstance($className, $data = array(), $recordID = null)
	{
		return new $className($data, $recordID);
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
		$instance = $className::retrieveFromPool($className, $recordID);

		if ($instance == null || !is_object($instance))
		{
			$instance = self::getNewInstance($className, $data, $recordID);
			$instance->setID($recordID, false);
			$instance->storeToPool();
		}
		else if (!$instance->isLoaded() && !empty($data))
		{
			$instance->createDataAccessVariables($data, $recordID);
		}

		if ($loadRecordData)
		{
			$instance->load($loadReferencedRecords);
		}

		return $instance;
	}

	/**
	 * Returns an existing record instance if it exists - otherwise a new instance will be returned
	 *
	 * @param string $className Class representing record
	 * @param mixed $recordID
	 *
	 * @return ActiveRecord
	 */
	public static function getInstanceByIdIfExists($className, $recordID, $returnNewIfNotExist = true)
	{
		if (self::objectExists($className, $recordID))
		{
			$instance = self::getInstanceByID($className, $recordID, self::LOAD_DATA);
		}
		else if ($returnNewIfNotExist)
		{
			$instance = self::getNewInstance($className);
			$instance->setID($recordID);
		}
		else
		{
			return null;
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
		self::$recordPool[$className][$hash] = null;
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
		self::$recordPool = null;
		self::$recordPool = array();

		self::$toArrayData = null;
		self::$toArrayData = array();
	}

	/**
	 * Stores ActiveRecord subclass instance in a record pool
	 *
	 * @param ActiveRecord $instance
	 */
	protected function storeToPool()
	{
		self::$recordPool[get_class($this)][self::getRecordHash($this->getID())] = $this;
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
			if ($recordID instanceof ActiveRecord)
			{
				$recordID = $recordID->getID();
			}

			$hash = self::getRecordHash($recordID);

			if (!empty(self::$recordPool[$className][$hash]))
			{
				if (self::$recordPool[$className][$hash] instanceof $className)
				{
					return self::$recordPool[$className][$hash];
				}
			}

			return null;
		}
		else if (isset(self::$recordPool[$className]))
		{
			return self::$recordPool[$className];
		}

		return array();
	}

	/**
	 * Gets a unique string representing concrete record
	 *
	 * @param mixed $recordID
	 * @return string
	 */
	protected static function getRecordHash($recordID)
	{
		if (!is_array($recordID))
		{
			return $recordID;
		}
		else
		{
			ksort($recordID);
			return implode("-", $recordID);
		}
	}

	/**
	 * Creates a select query object for a table identified by an ActiveRecord class name
	 *
	 * @param string $className
	 * @param bool $loadReferencedRecords Join records on foreign keys?
	 * @return string
	 */
	public static function createSelectQuery($className, &$loadReferencedRecords = false)
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

		$loadReferencedRecords = self::addAutoReferences($schema, $loadReferencedRecords);

		if ($loadReferencedRecords)
		{
			self::joinReferencedTables($schema, $query, $loadReferencedRecords);
		}
		return $query;
	}

	private static function addAutoReferences(ARSchema $schema, $loadReferencedRecords)
	{
		if (true === $loadReferencedRecords)
		{
			return true;
		}

		// auto-referenced tables
		if ($autoReferences = $schema->getRecursiveAutoReferences())
		{
			if (!is_array($loadReferencedRecords))
			{
				$loadReferencedRecords = array();
			}

			// clear already included references
			foreach ($autoReferences as $key => $ref)
			{
				if (is_numeric(array_search($ref, $loadReferencedRecords)) && is_numeric($key))
				{
					unset($autoReferences[$key]);
				}
			}

			return array_merge($loadReferencedRecords, $autoReferences);
		}

		return $loadReferencedRecords;
	}

	private function array_invert($arr)
	{
		$flipped = array();
		foreach(array_keys($arr) as $key)
		{
			if(array_key_exists($arr[$key],$flipped))
			{
				$flipped[$arr[$key]] = array_merge((array)$flipped[$arr[$key]], (array)$key);
			}
			else
			{
				$flipped[$arr[$key]] = $key;
			}
		}

		return $flipped;
	}

	/* @todo: document possible loadReferenceRecords values */
	protected static function joinReferencedTables(ARSchema $schema, ARSelectQueryBuilder $query, &$loadReferencedRecords = false)
	{
		// do not use auto-references for single-table one level joins
		if (!is_string($loadReferencedRecords))
		{
			$loadReferencedRecords = self::addAutoReferences($schema, $loadReferencedRecords);
		}

		$tables = is_array($loadReferencedRecords) ? self::array_invert($loadReferencedRecords) : $loadReferencedRecords;

		$referenceList = $schema->getReferencedForeignKeyList();
		$schemaName = $schema->getName();

		foreach($referenceList as $name => $field)
		{
			$foreignClassName = $field->getForeignClassName();
			$tableAlias = $field->getReferenceName();
			$foreignSchema = self::getSchemaInstance($foreignClassName);
			$foreignTableName = $foreignSchema->getName();

			$aliasParts = explode('_', $tableAlias);
			$aliasName = isset($aliasParts[1]) ? $aliasParts[1] : '';

			$isSameSchema = $schema === $foreignSchema;
			$notRequiredForInclusion = is_array($tables) && !isset($tables[$foreignClassName]);
			$isAliasSpecified = is_array($tables) && isset($tables[$foreignClassName]) && !is_numeric($tables[$foreignClassName]);

			if ($isAliasSpecified)
			{
				$classNamesDoNotMatch = $tables[$foreignClassName] != $aliasName;
				$notReferencedAsArray = !is_array($tables[$foreignClassName]);
				$notInReferencedArray = is_array($tables[$foreignClassName]) && !in_array($aliasName, $tables[$foreignClassName]);
			}

			if ($tables !== $schemaName)
			{
				if ($isSameSchema || $notRequiredForInclusion ||
						($isAliasSpecified && $classNamesDoNotMatch && ($notReferencedAsArray || $notInReferencedArray))
					 || (is_string($tables) && ($tables != $schemaName))
					)
				{
					continue;
				}
			}

			if (!$query->getJoinsByClassName($foreignTableName))
			{
				$tableAlias = $foreignTableName;
			}

			$joined = $query->joinTable($foreignTableName, $schemaName, $field->getForeignFieldName(), $name, $tableAlias);

			if ($joined)
			{
				foreach($foreignSchema->getFieldList() as $foreignFieldName => $foreignField)
				{
					$query->addField($foreignFieldName, $tableAlias, $tableAlias."_".$foreignFieldName);
				}

				self::getLogger()->logQuery('Joining ' . $foreignClassName . ' on ' . $schemaName);

				self::joinReferencedTables($foreignSchema, $query, $loadReferencedRecords);
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
		if ($this->isLoaded || !$this->isExistingRecord() || $this->isDeleted())
		{
			return false;
		}

		$query = self::createSelectQuery(get_class($this), $loadReferencedRecords);
		$this->loadData($loadReferencedRecords, $query);
		$this->isDeleted = false;

		return true;
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

		$this->createDataAccessVariables($parsedRowData['recordData'], $this->getID());

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

	private function getUsedSchemas($schema, $referencedSchemaList)
	{
		$loadReferencedRecords = $referencedSchemaList;
		$schemas = is_string($referencedSchemaList) ? $schema->getDirectlyReferencedSchemas() : $schema->getReferencedSchemas();

		// remove schemas that were not loaded with this query
		if (is_array($loadReferencedRecords))
		{
			$loadReferencedRecords = self::array_invert($loadReferencedRecords);
			$filteredSchemas = array();

			foreach($loadReferencedRecords as $tableName => $tableAlias)
			{
				if (is_numeric($tableAlias))
				{
					if (!isset($schemas[$tableName]))
					{
						if (isset($schemas[$tableName . '_' . $tableName]))
						{
							$tableName .= '_' . $tableName;
						}
						else
						{
							$break = false;
							foreach ($schemas as $name => $collection)
							{
								foreach ($collection as $schema)
								{
									if ($schema->getName() == $tableName)
									{
										$tableName = $name;
										$break = true;
										break;
									}
								}

								if ($break)
								{
									break;
								}
							}
						}
					}

					$filteredSchemas[$tableName] = $schemas[$tableName][0];
				}
				else
				{
					$originalAlias = $tableAlias;
					$aliases = !is_array($tableAlias) ? array($tableName . '_' . $tableAlias) : $tableAlias;

					foreach ($aliases as $aliasIndex => $tableAlias)
					{
						// If the same table is referenced for two or more times an alias that consists of foreign key
						// and referenced table name is used. However the first instance is always referenced using the
						// table name only to avoid having to specify the full aliases in all WHERE conditions
						if (!isset($schemas[$tableAlias]))
						{
							if (0 == $aliasIndex)
							{
								$tableAlias = $tableName;
							}
							else
							{
								$tableAlias = $tableName . '_' . $tableAlias;
							}
						}

						if (!isset($schemas[$tableAlias]))
						{
							$tableAlias = $originalAlias;
						}

						if (is_array($tableAlias))
						{
							$tableAlias = $tableName . '_' . array_pop($tableAlias);
						}

						foreach($schemas[$tableAlias] as $key => $unfilteredSchema)
						{
							if ($unfilteredSchema->getName() == $tableName)
							{
								$filteredSchemas[$tableAlias] = $unfilteredSchema;
							}
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

		return $schemas;
	}

	private static function extractSchemaData(ARSchema $schema, &$dataArray, $transformArray)
	{
		foreach($schema->getArrayFieldList() as $name)
		{
			$dataArray[$name] = is_string($dataArray[$name]) ? @unserialize($dataArray[$name]) : '';
		}

		$recordData = array_intersect_key($dataArray, $schema->getFieldList());
		$dataArray = array_diff_key($dataArray, $recordData);

		if ($transformArray)
		{
			$recordData = call_user_func_array(array($schema->getName(), 'transformArray'), array($recordData, $schema));
		}

		return $recordData;
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
		$usedColumns = array();

		$recordData = self::extractSchemaData($schema, $dataArray, $transformArray);

		if ($loadReferencedRecords && $dataArray)
		{
			$schemas = self::getUsedSchemas($schema, $loadReferencedRecords);
			$referenceListData = array_fill_keys(array_keys($schemas), array());

			// indicates if a schema has already been referenced
			$usedSchemas = array();

			foreach ($schemas as $referenceName => $foreignSchema)
			{
				$foreignSchemaName = $foreignSchema->getName();

				$newRefFound = false;
				if (is_array($loadReferencedRecords) && isset($loadReferencedRecords[$referenceName]))
				{
					$newRef = $loadReferencedRecords[$referenceName] . '_' . $referenceName;
					foreach (array_keys($dataArray) as $key)
					{
						if (substr($key, 0, strlen($newRef)) == $newRef)
						{
							$fieldReferenceName = $referenceName = $newRef;
							$newRefFound = true;
							break;
						}
					}
				}

				if (!$newRefFound && !isset($usedSchemas[$foreignSchemaName]))
				{
					// if we have defaultImageID column linked to ProductImage table we need to have this data
					// identified by DefaultImage (column) rather than ProductImage (referenced class name)
					// as there could be multiple columns referencing different records in the same foreign table
					$fieldReferenceName = array_pop(explode('_', $referenceName));

					$referenceName = $foreignSchemaName;
				}

				// get field aliases that were used in the database query
				// for example, TableName_columnName
				$fieldNames = array_keys($foreignSchema->getFieldList());

				$referenceKeys = array();
				foreach($fieldNames as $fieldName)
				{
					$referenceKeys[$fieldName] = $referenceName . '_' . $fieldName;
				}

				$usedColumns = array_merge($usedColumns, array_values($referenceKeys));

				// unserialize array fields
				foreach ($foreignSchema->getArrayFieldList() as $fieldName)
				{
					$dataArray[$referenceKeys[$fieldName]] = is_string($dataArray[$referenceKeys[$fieldName]]) ? @unserialize($dataArray[$referenceKeys[$fieldName]]) : '';
				}

				$referencedRecord = array_combine($fieldNames, array_intersect_key($dataArray, array_flip($referenceKeys)));

				$referenceListData[$referenceName] = $referencedRecord;

				// initialize complete data structure using references
				foreach ($foreignSchema->getForeignKeyList() as $fieldName => $field)
				{
					if ($foreignSchemaName != $field->getForeignTableName())
					{
						$referenceListData[$referenceName][$field->getReferenceFieldName()] =& $referenceListData[$field->getForeignTableName()];
					}
				}

				if ($transformArray)
				{
					// extract non-null ID values to determine if the record has data that needs to be transformed
					if (array_diff(array_intersect_key($referenceListData[$referenceName], $foreignSchema->getPrimaryKeyList()), array(null)))
					{
						$referenceListData[$referenceName] = call_user_func_array(array($foreignSchemaName, 'transformArray'), array($referenceListData[$referenceName], $foreignSchema));
					}
				}

				if (!isset($recordData[$fieldReferenceName]))
				{
					$recordData[$fieldReferenceName] = $referenceListData[$referenceName];
				}

				if (!$newRefFound)
				{
					$usedSchemas[$foreignSchemaName] = true;
				}
			}
		}

		return array("recordData" => $recordData, "referenceData" => $referenceListData, "miscData" => array_diff_key($dataArray, array_flip($usedColumns)));
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

		return self::createRecordSet($className, $query, $loadReferencedRecords);
	}

	public function initSet()
	{
		$set = $this->getEmptySet();
		$set->add($this);
		return $set;
	}

	public static function fetchDataFromDB(ARSelectQueryBuilder $query)
	{
		$db = self::getDBConnection();

		$queryStr = $query->createString();
		self::getLogger()->logQuery($queryStr);
		$resultSet = $query->getPreparedStatement($db)->executeQuery();
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
		self::getLogger()->logQuery($sqlSelectQuery);

		if ($sqlSelectQuery instanceof PreparedStatementCommon)
		{
			$resultSet = $sqlSelectQuery->executeQuery();
		}
		else
		{
			$db = self::getDBConnection();
			$resultSet = $db->executeQuery($sqlSelectQuery);
		}

		self::getLogger()->logQueryExecutionTime();

		$dataArray = array();
		while ($resultSet->next())
		{
			$dataArray[] = $resultSet->getRow();
		}
		return $dataArray;
	}

	public static function getDataByQuery(ARSelectQueryBuilder $query)
	{
		return self::getDataBySQL($query->getPreparedStatement(self::getDBConnection()));
	}

	/**
	 *	Return values only of particular columns
	 *
	 *	@return array
	 */
	public static function getFieldValues($className, ARSelectFilter $filter, $fields, $loadReferencedRecords = array())
	{
		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);
		$query->removeFieldList();

		foreach ($fields as $tableName => $fieldName)
		{
			$query->addField($fieldName, is_numeric($tableName) ? $className : $tableName);
		}

		return self::getDataBySQL($query->createString());
	}

	public static function executeUpdate($sql)
	{
		try
		{
			self::getLogger()->logQuery($sql);
			$res = self::getDBConnection()->executeQuery($sql);
			self::getLogger()->logQueryExecutionTime();
			return $res;
		}
		catch (Exception $e)
		{
			self::getLogger()->logQuery(get_class($e) . ': ' . $e->getMessage());
			throw $e;
		}
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

		$recordSet = self::getEmptySet($className, $query->getFilter());
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
		if ((($filter->getLimit() > 0) && ($filter->getLimit() >= $recordSet->size())) || $filter->getOffset() > 0)
		{
			$db = self::getDBConnection();
			$counterFilter = clone $filter;
			$counterFilter->removeFieldList();
			$counterFilter->setLimit(0, 0);

			$query->removeFieldList();
			$query->addField("COUNT(*)", null, "totalCount");
			$query->setFilter($counterFilter);

			$recordSet->setCounterQuery($query->getPreparedStatement($db), $db);
		}

		return $recordSet;
	}

	private function getEmptySet($className = null, ARSelectFilter $filter = null)
	{
		$className = is_null($className) ? get_class($this) : $className;
		$setClassName = class_exists($className . 'Set', false) ? $className . 'Set' : 'ARSet';

		if (!is_subclass_of($setClassName, 'ARSet'))
		{
			$setClassName = 'ARSet';
		}

		return new $setClassName($filter);
	}

	public static function getRecordCount($className, ARSelectFilter $filter, $referencedTables = array())
	{
		$db = self::getDBConnection();
		$counterFilter = clone $filter;
		$counterFilter->removeFieldList();
		$counterFilter->setLimit(0, 0);

		$query = new ARSelectQueryBuilder();
		self::joinReferencedTables(self::getSchemaInstance($className), $query, $referencedTables);
		$query->removeFieldList();
		$query->addField("COUNT(*)", null, "totalCount");
		$query->includeTable($className);
		$query->setFilter($counterFilter);

		$counterQuery = $query->createString();

		self::getLogger()->logQuery($counterQuery);

		$counterResult = $query->getPreparedStatement($db)->executeQuery();
		$counterResult->next();

		$resultData = $counterResult->getRow();
		return $resultData['totalCount'];
	}

	public static function getRecordCountByQuery(ARSelectQueryBuilder $query)
	{
		$query = clone $query;
		$query->removeFieldList();
		$query->addField("COUNT(*)", null, "totalCount");

		// in case there is a HAVING condition, we need to add GROUP BY to get the correct count
		$filter = $query->getFilter();
		if ($filter)
		{
			if ($filter->isHavingConditionSet())
			{
				$filter->setGrouping(new ARExpressionHandle('2'));
			}
			else
			{
				$filter->setGrouping(new ARExpressionHandle('NULL'));
			}
		}

		$counterQuery = $query->createString();

		self::getLogger()->logQuery($counterQuery);

		$db = self::getDBConnection();
		$counterResult = $query->getPreparedStatement($db)->executeQuery();
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
	public function getRelatedRecordSet($foreignClassName, ARSelectFilter $filter = null, $loadReferencedRecords = false)
	{
		if (is_null($filter))
		{
			$filter = new ARSelectFilter();
		}

		$this->appendRelatedRecordJoinCond($foreignClassName, $filter);

		return self::getRecordSet($foreignClassName, $filter, $loadReferencedRecords);
	}

	public function getRelatedRecordCount($foreignClassName, ARSelectFilter $filter = null, $loadReferencedRecords = false)
	{
		if (is_null($filter))
		{
			$filter = new ARSelectFilter();
		}

		$this->appendRelatedRecordJoinCond($foreignClassName, $filter);
		$query = self::createSelectQuery($foreignClassName, $loadReferencedRecords);
		$query->getFilter()->merge($filter);

		return $this->getRecordCountByQuery($query);
	}

	public static function getAggregate($className, $function, ARFieldHandleInterface $handle, ARSelectFilter $filter, $loadReferencedRecords = null)
	{
		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);
		$query->removeFieldList();
		$query->addField($function . '(' . $handle->toString() . ')', null, 'result');

		$res = self::getDataByQuery($query);

		return $res[0]['result'];
	}

	public function getRelatedAggregate($className, $function, ARFieldHandleInterface $handle, ARSelectFilter $filter = null, $loadReferencedRecords = array())
	{
		if (!$filter)
		{
			$filter = new ARSelectFilter();
		}

		$loadReferencedRecords[] = get_class($this);

		$this->appendRelatedRecordJoinCond($className, $filter);

		return self::getAggregate($className, $function, $handle, $filter, $loadReferencedRecords);
	}

	private function appendRelatedRecordJoinCond($foreignClassName, ARFilter $filter)
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
	public static function deleteRecordSet($className, ARDeleteFilter $filter, $cleanUp = false, $joinReferencedTables = false)
	{
		$schema = self::getSchemaInstance($className);
		$db = self::getDBConnection();

		$query = new ARSelectQueryBuilder();
		$query->includeTable($className);

		if ($joinReferencedTables)
		{
			self::joinReferencedTables($schema, $query, $joinReferencedTables);
		}

		$query->setFilter($filter);
		$query->removeFieldList();

		$deleteQuery = preg_replace('/^SELECT[ ]*FROM/', 'DELETE ' . $className . '.* FROM', $query->createString());

		if($cleanUp && isset(self::$recordPool[$className]))
		{
			foreach(self::$recordPool[$className] as $record)
			{
				$record->markAsNotLoaded();
				$record->markAsDeleted();
			}
		}

		self::getLogger()->logQuery($deleteQuery);
		$res = $db->executeUpdate($deleteQuery);
		self::getLogger()->logQueryExecutionTime();
		return $res;
	}

	public function deleteRelatedRecordSet($className, ARDeleteFilter $filter = null, $joinReferencedTables = false)
	{
		if (!$filter)
		{
			$filter = new ARDeleteFilter();
		}

		$this->appendRelatedRecordJoinCond($className, $filter);

		return self::deleteRecordSet($className, $filter, null, $joinReferencedTables);
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

		$hash = self::getRecordHash($recordID);
		if(isset(self::$recordPool[$className][$hash]))
		{
			$record = self::$recordPool[$className][$hash];
			$record->markAsNotLoaded();
			$record->markAsDeleted();
		}

		$filter->setCondition($PKValueCond);
		self::deleteRecordSet($className, $filter, false);
	}

	/**
	 * Updates a set of records by using ARUpdateFilter
	 *
	 * @param class $className
	 * @param ARUpdateFilter $filter
	 * @return unknown
	 */
	public static function updateRecordSet($className, ARUpdateFilter $filter, $joinReferencedTables = false)
	{
		$schema = self::getSchemaInstance($className);
		$db = self::getDBConnection();

		$query = new ARSelectQueryBuilder();
		$query->includeTable($className);

		if ($joinReferencedTables)
		{
			//$tables = is_array($joinReferencedTables) ? array_flip($joinReferencedTables) : $joinReferencedTables;
			self::joinReferencedTables($schema, $query, $joinReferencedTables);
		}

		$query->setFilter($filter);
		$query->removeFieldList();

		$sql = preg_replace('/^SELECT[ ]*FROM/', 'UPDATE', $query->createString());

		self::getLogger()->logQuery($sql);
		return $db->executeUpdate($sql);
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
		if (!$this->isExistingRecord() && !$this->isModified())
		{
			$this->resetModifiedStatus(true);
		}

		if (!$this->isModified() || $this->isDeleted())
		{
			return false;
		}

		$this->setupDBConnection();
		if ($forceOperation)
		{
		  	$action = ($forceOperation == self::PERFORM_UPDATE) ? self::PERFORM_UPDATE : self::PERFORM_INSERT;
		}
		else
		{
			$action = $this->isExistingRecord() ? self::PERFORM_UPDATE : self::PERFORM_INSERT;
		}

		if (self::PERFORM_UPDATE == $action)
		{
			$res = $this->update();
		}
		else
		{
			$res = $this->insert();
		}

		$this->markAsLoaded();

		return $res;
	}

	public function resetModifiedStatus($isModified = false)
	{
		foreach($this->data as $dataContainer)
		{
			$dataContainer->resetModifiedStatus($isModified);
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

		$modified = $this->enumerateModifiedFields();
		if (!$modified)
		{
			return;
		}

		$updateQuery = "UPDATE " . $this->schema->getName() . " SET " . $this->enumerateModifiedFields() . " " . $filter->createString();

		$result = $this->executeUpdate($updateQuery);

		$this->resetModifiedStatus();

		return $result;
	}

	/**
	 * Creates a new persisted instance of activerecord (Inserts a new record to a database)
	 *
	 * @return int Rows affected
	 */
	protected function insert()
	{
		$insertQuery = "INSERT INTO ".$this->schema->getName()." SET ".$this->enumerateModifiedFields();

		$result = $this->executeUpdate($insertQuery);

		// get inserted record ID
		if (count($this->schema->getPrimaryKeyList()) == 1)
		{
			$PKList = $this->schema->getPrimaryKeyList();
			$PKField = $PKList[key($PKList)];
			if (($PKField->getDataType() instanceof ARInteger) && !$this->getID())
			{
				$IDG = $this->db->getIdGenerator();
				$this->setID($IDG->getId(), false);
			}
		}

		$this->storeToPool();

		$this->resetModifiedStatus();

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

	public function updateRecord(ARUpdateFilter $filter)
	{
		$filter->mergeCondition($this->getRecordIDCondition());
		$updateQuery = "UPDATE " . $this->schema->getName() . $filter->createString();
		$res = $this->executeUpdate($updateQuery);
		$this->reload();
		return $res;
	}

	protected function getRecordIDCondition()
	{
		$PKList = $this->schema->getPrimaryKeyList();
		$className = get_class($this);

		foreach($PKList as $PKField)
		{
			$recordID = "";
			if ($PKField instanceof ARForeignKey)
			{
				$recordID = $this->data[$PKField->getName()]->getInitialID();
			}
			else
			{
				$recordID = $this->data[$PKField->getName()]->get();
			}

			$fieldCond = new EqualsCond(new ARFieldHandle($className, $PKField->getName()), $recordID);
			if (isset($cond))
			{
				$cond->addAND($fieldCond);
			}
			else
			{
				$cond = $fieldCond;
			}
		}

		return $cond;
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
			$value = "NULL";

			//if (!($dataContainer->getField() instanceof ARPrimaryKeyField) && $dataContainer->isModified()) {
			if ($dataContainer->isModified())
			{
				if ($dataContainer->getField() instanceof ARForeignKey)
				{
					if (!$dataContainer->isNull() && !is_null($dataContainer->get()))
					{
						$id = $dataContainer->get()->getID();
						$value = !is_null($id) ? "'" . $id . "'" : 'NULL';
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
						$value = (float)$value;
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

				if (is_object($value))
				{
					$value = $value->__toString();
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

			if ($schema->getField($fieldName)->getDataType() instanceof ARNumeric)
			{
				$recordID = (float)$recordID;
			}

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

	public function getSchema()
	{
		return $this->schema;
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

		$foreignKeys = array();

		foreach($this->data as $name => $value)
		{
			$fieldValue = $value->get();

			if ($value->getField() instanceof ARForeignKey)
			{
				if ($fieldValue != null)
				{
					$foreignKeys[$name] = $value;
					$data[$name] = $fieldValue->getID();
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

		// process referenced records
		foreach ($foreignKeys as $name => $value)
		{
			$fieldValue = $value->get();

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
				$data[$varName] = &$fieldValue->toArray($force);
			}
		}

		$data = call_user_func_array(array($className, 'transformArray'), array($data, $this->schema));

		if (!$this->isLoaded())
		{
			unset(self::$toArrayData[$currentIdentifier]);
		}

		return $data;
	}

	protected function getRecordIdentifier(ActiveRecord $record)
	{
		return get_class($record) . '-' . self::getRecordHash($record->getID());
	}

	protected function setArrayData($array)
	{
		self::$toArrayData[$this->getRecordIdentifier($this)] = $array;
	}

	public static function getArrayData($identifier)
	{
		if (isset(self::$toArrayData[$identifier]))
		{
			return self::$toArrayData[$identifier];
		}
		else if (isset(self::$toArrayData['raw-' . $identifier]))
		{
			return self::$toArrayData['raw-' . $identifier];
		}
	}

	public function resetArrayData()
	{
		unset(self::$toArrayData[$this->getRecordIdentifier($this)]);
	}

	public function clearArrayData()
	{
		self::$toArrayData = array();
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
	protected static function &transformArray($array, ARSchema $schema)
	{
		if (!empty($array['ID']))
		{
			$id = 'raw-' . $schema->getName() . '-' . self::getRecordHash($array['ID']);
			self::$toArrayData[$id] =& $array;

			return self::$toArrayData[$id];
		}

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

		$resultDataArray = array();

		$schema = self::getSchemaInstance($className);

		$data = self::fetchDataFromDB($query);

		foreach($data as $rowData)
		{
			$parsedRowData = self::prepareDataArray($className, $schema, $rowData, $loadReferencedRecords, self::TRANSFORM_ARRAY);
			$resultDataArray[] = array_merge($parsedRowData['recordData'], $parsedRowData['referenceData'], $parsedRowData['miscData']);
		}

		if (!is_null($getRecordCount))
		{
			$getRecordCount = self::getRecordCountByQuery($query);
		}
		//v_ar_dump($className . ' | ' . round(memory_get_usage() / (1024*1024), 1));
		return $resultDataArray;
	}

	public static function getRecordSetFields($className, ARSelectFilter $filter, $fields, $loadReferencedRecords = false)
	{
		$query = self::createSelectQuery($className, $loadReferencedRecords);
		$query->getFilter()->merge($filter);

		$query->removeFieldList();
		foreach ($fields as $field)
		{
			$query->addField($field);
		}

		return self::fetchDataFromDB($query);

		$resultDataArray = array();

		$schema = self::getSchemaInstance($className);

		$data = self::fetchDataFromDB($query);

		foreach($data as $rowData)
		{
			$parsedRowData = self::prepareDataArray($className, $schema, $rowData, $loadReferencedRecords, self::TRANSFORM_ARRAY);
			$resultDataArray[] = array_merge($parsedRowData['recordData'], $parsedRowData['referenceData'], $parsedRowData['miscData']);
		}

		if (!is_null($getRecordCount))
		{
			$getRecordCount = self::getRecordCountByQuery($query);
		}
		//v_ar_dump($className . ' | ' . round(memory_get_usage() / (1024*1024), 1));
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
			$instance = $className::retrieveFromPool($className, $id);

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
		return $this->load($loadReferencedRecords);
	}

	/**
	 * Mark as deleted record
	 */
	public function markAsDeleted()
	{
		if ($this->isExistingRecord())
		{
			$this->isDeleted = true;
		}
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
		if (self::$transactionLevel < 0)
		{
			self::$transactionLevel = 0;
		}

		self::getLogger()->logAction("COMMIT transaction" . ((int)self::$transactionLevel + 1));
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

	public function setCustomSerializeData($key, $value)
	{
		$this->customSerializeData[$key] = $value;
	}

	public function getCustomSerializeData($key)
	{
		if (isset($this->customSerializeData[$key]))
		{
			return $this->customSerializeData[$key];
		}
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
				if (!$value || !is_object($value) || !$value->getID())
				{
					continue;
				}

				$value = new ARSerializedReference($value);
			}

			$serialized['data'][$key] = $value;
		}

		// serialize custom variables
		$properties[] = 'isLoaded';
		$properties[] = 'customSerializeData';

		foreach ($properties as $key)
		{
			$serialized[$key] = $this->$key;
		}

		$s = serialize($serialized);

//		echo 'Ending '.get_class($this) . "\n"; flush();

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

		$variables = array();
		foreach ($array['data'] as $key => $valueMapper)
		{
			$variables[$key] = $valueMapper instanceof ARValueMapper ? $valueMapper->get() : $valueMapper;
		}

		//var_dump($variables);
		$this->createDataAccessVariables($variables);
		unset($array['data']);

		foreach ($array as $key => $value)
		{
			$this->$key = $value;
		}

		if ($this->isLoaded())
		{
			$this->storeToPool();
		}
	}

	public function destruct($references = array())
	{
		$this->isDestructing = true;

		if (is_array($references))
		{
			foreach ($references as $field)
			{
				$this->data[$field]->destructValue();
			}
		}
		else
		{
			foreach (array_keys($this->data) as $field)
			{
				$this->data[$field]->destructValue();
			}
		}
	}

	public function isDestructing()
	{
		return $this->isDestructing;
	}

	public function __destruct()
	{
		$this->isDestructing = true;
		//self::removeFromPool($this);
		//logDestruct($this, $this->getID());
	}

	public function __clone()
	{
		$this->cachedId = null;
		$this->originalRecord = self::getInstanceByID(get_class($this), $this->getID());

		foreach ($this->data as $key => $valueMapper)
		{
			$this->data[$key] = clone $valueMapper;
			$this->$key = $this->data[$key];
		}

		foreach ($this->schema->getForeignKeyList() as $name => $field)
		{
			$referenceName = $field->getReferenceFieldName();
			$referenceName = strtolower(substr($referenceName, 0, 1)).substr($referenceName, 1);
			$this->$referenceName = $this->data[$name];
		}

		$this->resetID();
	}

	public function __get($name)
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

register_shutdown_function(array('ActiveRecord', 'clearPool'));

?>
