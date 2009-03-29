<?php

/**
 * ActiveRecord query / action logger
 *
 * @package activerecord
 * @author Integry Systems
 */
class ARLogger
{
	private $isEnabled = true;
	private $startTime = null;
	private $outputType = 1;
	private $logFileName = "";

	private $lastQueryTime = null;

	const OUTPUT_FILE = 1;
	const OUTPUT_SCREEN = 2;

	const LOG_ACTION = 1;
	const LOG_OBJECT = 2;
	const LOG_QUERY = 3;

	public static $queryTimes = array();

	public function __construct()
	{
		$this->startTime = microtime(true);
	}

	public function logQuery($queryStr)
	{
		if (!$this->logFileName)
		{
			return null;
		}

		if ($queryStr instanceof PreparedStatementCommon)
		{
			$queryStr = $queryStr->getSQL() . "\n" . var_export($queryStr->getValues(), true);
		}

		$this->lastQueryTime = microtime(true);
		$this->addLogItem($queryStr, self::LOG_QUERY);
	}

	public function logObject(ActiveRecord $ARInstance, $restoredFromPool = false)
	{
		return false;
		$ID = $ARInstance->getID();

		if ($ARInstance->isLoaded())
		{
			$loadedStr = "data is loaded";
		}
		else
		{
			$loadedStr = "no data loaded";
		}

		if ($restoredFromPool)
		{
			$msg = "Restoring object from pool (".get_class($ARInstance).":$ID, $loadedStr)";
		}
		else
		{
			$msg = "Building new object (".get_class($ARInstance).":$ID, $loadedStr)";
		}
		$this->addLogItem($msg, self::LOG_OBJECT);
	}

	public function logQueryExecutionTime()
	{
		if (!$this->logFileName)
		{
			return null;
		}

		$time = microtime(true) - $this->lastQueryTime;
		self::$queryTimes[] = array($this->lastQuery, $time, $this->lastTrace);
		file_put_contents($this->logFileName, '( ' . $time . ' sec)' . "\n\n", FILE_APPEND);
	}

	private function addLogItem($msg, $logType)
	{
		if (!$this->logFileName)
		{
			return null;
		}

		$logItem = array("type" => $logType, "msg" => $msg);

		$logData = /*$this->startTime.*/ microtime(true) . " | " . $this->createLogItemStr($logItem);

		file_put_contents($this->logFileName, $logData, FILE_APPEND);

		$e = new Exception();
		$this->lastTrace = ApplicationException::getFileTrace($e->getTrace());
		$this->lastQuery = $msg;
	}

	private function createLogItemStr($itemArray)
	{
		$str = str_repeat('	', ActiveRecord::$transactionLevel);
		$str .= $itemArray['msg']."\n";
		return $str;
	}

	public function logAction($actionInfo)
	{
		$this->addLogItem($actionInfo, self::LOG_ACTION);
	}

	public function enable()
	{
		$this->isEnabled = true;
	}

	public function disable()
	{
		$this->isEnabled = false;
	}

	public function setLogFileName($path)
	{
		$this->logFileName = $path;
	}
}

?>