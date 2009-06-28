<?php

require_once dirname(__FILE__) . '/Initialize.php';

ClassLoader::import('library.activerecord.ARSerializableDateTime');
ClassLoader::import('application.model.product.Product');
ClassLoader::import('application.model.discount.DiscountCondition');

/**
 * @author Integry Systems
 * @package test.activerecord
 */
class MemoryUsageTest extends UnitTest
{
	public function getUsedSchemas()
	{
		return array('Product');
	}

	public function XtestInstanceMemoryUsage()
	{
		$f = new ARSelectFilter();
		$f->setLimit(1000);
		ActiveRecord::getRecordSetArray('Product', $f);

		$arrayMem = memory_get_usage();
		$array = ActiveRecord::getRecordSetArray('Product', $f);
		$arrayMem = memory_get_usage() - $arrayMem;

		echo $arrayMem . "\n";

		$arraySet = memory_get_usage();
		$array = ActiveRecord::getRecordSet('Product', $f);
		$arraySet = memory_get_usage() - $arraySet;

		echo $arraySet . "\n";
	}

	public function testInstanceMemoryUsage()
	{
		$f = new ARSelectFilter();
		$f->setLimit(1000);
		ActiveRecord::getRecordSetArray('DiscountCondition', $f);

		$arrayMem = memory_get_usage();
		$array = ActiveRecord::getRecordSetArray('DiscountCondition', $f);
		$arrayMem = memory_get_usage() - $arrayMem;

		echo count($arrayMem) . "\n";
		echo $arrayMem . "\n";

		$arraySet = memory_get_usage();
		$array = ActiveRecord::getRecordSet('DiscountCondition', $f);
		$arraySet = memory_get_usage() - $arraySet;

		echo $arraySet . "\n";
	}
}

?>