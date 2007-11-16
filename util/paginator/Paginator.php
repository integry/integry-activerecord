<?php

/**
 * Helper class for displaying large record sets
 *
 * @package activerecord.util.paginator
 * 
 */
class Paginator {
	
	/**
	 * Record set instance
	 *
	 * @var ARSet
	 */
	private $recordSet = null;
	
	/**
	 * Paginator constructor
	 *
	 * @param ARSet $recordSet Record set to paginate
	 */
	public function __construct(ARSet $recordSet) {
		$this->recordSet = $recordSet;
	}
	
	public function getPageCount() {
		return ceil($this->getRecordCount() / $this->getPageSize());
	}
	
	public function getPageSize() {
		$filter = $this->recordSet->getFilter();
		return $filter->getLimit();
	}
	
	public function getRecordCount() {
		return $this->recordSet->getTotalRecordCount();
	}
	
	public function createPageList() {
		$pageList = array();
		$intervalEnd = "";
		for($i = 0; $i < $this->getPageCount(); $i++) {
			if (($i+1) * $this->getPageSize() > $this->getRecordCount()) {
				$intervalEnd = $this->getRecordCount();
			} else {
				$intervalEnd = ($i+1) * $this->getPageSize();
			}
			$pageList[] = array("number" => $i+1,  
								"from" => $i * $this->getPageSize() + 1, 
								"to" => $intervalEnd
								);
		}
		return $pageList;
	}
	
	public function getCurrentPage() {
		$filter = $this->recordSet->getFilter();
		return $filter->getOffset() / $filter->getLimit() + 1; 
	}
	
	/**
	 * Builds an assoc array representing record set pagination structure (probably this 
	 * information will be used by some template engine)
	 *
	 * Array structure (key list):
	 * pageCount - number of pages representing a record set
	 * recordCount - total count of records
	 * currentPage
	 * pageSize - page size in records
	 * list - list of generated pages (page info) with folowing structure
	 *	 number - page number
	 *	 from - record lower range that page starts from
	 *	 to - record upper range
	 * 
	 * 
	 * @return array
	 */
	public function toArray() {
		$result = array("pageCount" => $this->getPageCount(), 
						"recordCount" => $this->getRecordCount(), 
						"currentPage" => $this->getCurrentPage(),
						"pageSize" => $this->getPageSize(),
						"list" => $this->createPageList()
						);
		
		return $result;
	}
}

?>