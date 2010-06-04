<?php

include_once dirname(__FILE__) . '/ARFeed.php';

/**
 * Sequentially read and iterate over records from database
 *
 * This iterator can be used as data array in foreach and it allows to
 * read a very high number of records, as only a small chunk of them is
 * being kept in memory at any time.
 *
 * @author Integry Systems
 * @package application.model.
 */
class ARInstanceFeed extends ARFeed
{
	protected function loadData()
	{
		$this->data = ActiveRecord::getRecordSet($this->table, $this->filter, $this->referencedRecords)->getData();
	}

	protected function getChunkSize()
	{
		return 20;
	}

	public function current()
	{
		$instance = parent::current();
		ActiveRecord::removeFromPool($instance);
		return $instance;
	}
}