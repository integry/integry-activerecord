<?php

class ARSerializableDateTime extends DateTime implements Serializable
{
    private $dateString; 
    
    public function __construct($date)
    {
        $this->dateString = $date;
        parent::__construct($date);
    }
    
    public function serialize()
    {
        return serialize($this->dateString);
    }
    
    public function unserialize($serialized)
    {
        $this->dateString = unserialize($serialized);   
        parent::__construct($this->dateString);
    }    
}

?>