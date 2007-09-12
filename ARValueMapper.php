<?php

include_once(dirname(__file__) . '/schema/ARField.php');
include_once(dirname(__file__) . '/ARSerializableDateTime.php');

/**
 * Binds a value to a schema field
 * (it is just an ordinary row field value container)
 *
 * @package activerecord
 * @author Saulius Rupainis <saulius@integry.net>
 */
class ARValueMapper implements Serializable
{
	/**
	 * Schema field instance
	 *
	 * @var ARSchemaField
	 */
	private $field = null;

	/**
	 * Value assigned to a schema field
	 *
	 * @var mixed
	 */
	private $value = null;

	/**
	 * Initial ID value (reset after saving)
	 *
	 * Necessary when a record primary key values change and record needs to be updated (referencing the old ID values)
	 *
	 * @var mixed
	 */
	private $initialID = null;

	/**
	 * Initial value (reset after saving)
	 *
	 * Necessary to know when value changes change (other) objects state
	 *
	 * For example, changing products stock count from 0 to 5 would increase the count of the available 
     * products by 1, but changing the stock count from 5 to 10 wouldn't affect affect this count, so it's
     * sometimes not enough just to be able to check that the value has been changed.
	 *
	 * @var mixed
	 */
	private $initialValue = null;

	/**
	 * A mark to indicate if a record was modified
	 *
	 * @var bool
	 */
	private $isModified = false;

	private $isNull = false;

	public function __construct(ARField $field, $value = null)
	{
		$this->field = $field;
		$this->value = $value;
		
		if (($this->field->getDataType() instanceof ARDateTime) && !($value instanceof ARSerializableDateTime))
		{
            $this->value = new ARSerializableDateTime($this->value);
		}
		
        $this->initialValue = $value;
	}

	/**
	 * Gets a related schema field
	 *
	 * @return ARField
	 */
	public function getField()
	{
		return $this->field;
	}

	/**
	 * Sets a related schema field. 
	 *
	 * This method should never be called directly
	 *
	 * @return ARField
	 */
	public function setField(ARField $field)
	{
		$this->field = $field;
	}

	/**
	 * Assigns a value to a field
	 *
	 * @param mixed $value
	 */
	public function set($value = null, $markAsModified = true)
	{
	    if (is_null($value))
	    {
	        $this->setNull();
	        return false;
	    }
	    
	    // === also checks variable type, so we use == to compare values
        if (!is_null($this->initialValue) && (!is_object($value) && ($value == $this->value)) )
		{
            return false;
        }       
        
        if ($this->field instanceof ARForeignKey)
		{
            if (!($value instanceof ActiveRecord))
			{
                throw new ARException("Invalid value parameter: must be an instance of ActiveRecord");  
            }         
			else if (!is_a($value, $this->field->getForeignClassName()))
			{
                throw new ARException("Invalid value parameter: must be an instance of " . $this->field->getForeignClassName());	
			}  
		}		
		
		if ($this->field instanceof ARForeignKey && $this->value && !$this->initialID)
		{
			$this->initialID = $value->getID();
		}

		$this->isNull = false;
        $this->value = $value;
		
		if ($markAsModified)
		{
			$this->isModified = true;
		}
	}

	public function setNull($markAsModified = true)
	{
        $this->value = null;
		$this->isNull = true;
		if ($markAsModified)
		{
			$this->isModified = true;
		}
	}

	public function isNull()
	{
		return $this->isNull;
	}

	/**
	 * Gets a field value
	 *
	 * @return mixed
	 */
	public function get()
	{
		return $this->value;
	}

	/**
	 * Gets initial field ID value
	 *
	 * @return mixed
	 */
	public function getInitialID()
	{
		if (!$this->initialID)
		{
			return $this->value->getID();  
		}
		else
		{
			return $this->initialID;  
		}		
	}

	/**
	 * Gets initial field value
	 *
	 * @return mixed
	 */
	public function getInitialValue()
	{
		return $this->initialValue;  
	}

	/**
	 * Returns true if field is being modified
	 *
	 * @return bool
	 */
	public function isModified()
	{
		return $this->isModified;
	}

	/**
	 * Marks the field as not modified (after saving, etc.)
	 */
	public function resetModifiedStatus()
	{
		$this->isModified = false;

		if ($this->field instanceof ARForeignKey && !is_null($this->value))
		{
			$this->initialID = $this->value->getID();		  
		}

		$this->initialValue = $this->value;
	}

	/**
	 * Checks if this instance has an assigned value
	 *
	 * @return bool
	 */
	public function hasValue()
	{
		if ($this->value != null)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	public function serialize()
	{
        return serialize($this->value);
    }
    
    public function unserialize($serialized)
    {
        $this->set(unserialize($serialized), false);        
    }
	
	public function __clone()
	{
		$this->isModified = true;
		
		if ($this->field instanceof ARPrimaryKeyField)
		{
			$this->setNull(true);
			$this->value = null;
		}
	}
}

?>