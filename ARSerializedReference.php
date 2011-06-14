<?php

/**
 *
 * @package activerecord
 * @author Integry Systems
 */
class ARSerializedReference implements Serializable
{
	protected $instanceClassName;

	protected $instanceId;

	public function __construct(ActiveRecord $instance)
	{
		$this->instanceClassName = get_class($instance);
		$this->instanceId = $instance->getID();
	}

	public function restoreInstance()
	{
		$instance = ActiveRecord::getInstanceById($this->instanceClassName, $this->instanceId);
		return $instance;
	}

	public function getClassName()
	{
		return $this->instanceClassName;
	}

	public function serialize()
	{
		$serialized = array();
		$serialized['class'] = $this->instanceClassName;
		$serialized['ID'] = $this->instanceId;
		return serialize($serialized);
	}

	public function unserialize($serialized)
	{
		$data = unserialize($serialized);
		$this->instanceClassName = $data['class'];
		$this->instanceId = $data['ID'];
	}
}

?>