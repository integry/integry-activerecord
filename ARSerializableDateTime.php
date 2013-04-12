<?php

/**
 *
 * @package activerecord
 * @author Integry Systems
 */
class ARSerializableDateTime extends DateTime implements Serializable
{
	protected $dateString;

	private $isNull = false;

	public function __construct($dateString = false)
	{
		if ($dateString instanceof ARValueMapper)
		{
			$dateString = $dateString->get();
		}

		$this->dateString = $dateString;

		if(is_null($dateString) || '0000-00-00 00:00:00' == $dateString)
		{
			$this->isNull = true;
		}

		parent::__construct($dateString);
	}

	public static function createFromTimeStamp($timeStamp)
	{
		return new ARSerializableDateTime(date('Y-m-d H:i:s', $timeStamp));
	}

	public function isNull()
	{
		return $this->isNull;
	}

	public function format($format)
	{
		if($this->isNull())
		{
			return "";
		}
		else
		{
			return parent::format($format);
		}
	}

	public function getTimeStamp()
	{
		return $this->format("U");
	}

	public function getTime()
	{
		return (int)$this->format("U");
	}

	/**
	 *  Get a time difference in days from another DateTime object
	 */
	public function getDayDifference(DateTime $dateTime)
	{
		return $this->getSecDifference($dateTime) / 86400;
	}

	/**
	 *  Get a time difference in seconds from another DateTime object
	 */
	public function getSecDifference(DateTime $dateTime)
	{
		return $this->format("U") - $dateTime->format("U");
	}

	public function serialize()
	{
		return serialize(array(
			'dateString' => $this->dateString,
			'isNull' => ($this->isNull() ? 'true' : 'false')
		));
	}

	public function unserialize($serialized)
	{
		$dateArray = unserialize($serialized);
		if($dateArray['isNull'] == 'true')
		{
			$dateString = null;
		}
		else
		{
			$dateString = $dateArray['dateString'];
		}

		self::__construct($dateString);
	}

	public function __toString()
	{
		return $this->format('Y-m-d H:i:s');
	}
}

?>