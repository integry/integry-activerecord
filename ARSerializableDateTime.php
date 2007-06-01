<?php

class ARSerializableDateTime extends DateTime implements Serializable
{
    private $dateString; 
    
    private $isNull = false;
    
    public function __construct($dateString = false)
    {
        $this->dateString = $dateString;
        
        if(is_null($dateString) || '0000-00-00 00:00:00' == $dateString)
        {
            $this->isNull = true;
        }
        
        parent::__construct($dateString);
    }
    
    
    public function isNull()
    {
        return $this->isNull;
    }
    
    public function format($format)
    {
        if($this->isNull())
        {
            return null;
        }
        else
        {
            return parent::format($format);
        }
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