<?php
class MongoPager
{
	public $viewableRange = 4;
	private $totalCount = 0;
	function __construct($site, $obj)
	{
		$this->site = $site;
		$this->obj = $obj;
		$this->totalCount = $this->obj->count();
	}
	public function page($curPage, $totalCount, $pageSize = 10)
	{
		$this->numberOfPages = ceil($totalCount / $pageSize);
		$this->currentPage = $curPage;
		$this->pageSize = $pageSize;
		return $this->obj->skip(($pageSize * ($curPage - 1)))->limit($pageSize);
	}
	public function currentCount()
	{
		return count(iterator_to_array($this->obj));
	}
	public function totalCount()
	{
		return $this->totalCount;
	}
	public function pagesInRange()
	{
		$half = round( $this->viewableRange / 2 );
		// min
		$min = ($this->currentPage - $half);
		if($min < 1)
		{
			$min = 1;
		}
		// max
		$max = ($this->currentPage + $half);
		if($max > ($this->numberOfPages))
		{
			$max = $this->numberOfPages;
		}
		return range($min, $max);
	}
	public function previousPage()
	{
		return ($this->currentPage < 2) ? 1 : ($this->currentPage - 1);
	}
	public function nextPage()
	{
		return ($this->currentPage < $this->numberOfPages) ? ($this->currentPage + 1) : $this->numberOfPages;
	}
	public function numberOfPages()
	{
		return $this->numberOfPages;
	}
	public function getPagination()
	{
		return array('pages' => $this->pagesInRange(),
							'pageUtil' => new PageUtil($this->site),
							'currentPage' => $this->currentPage,
							'previousPage' => $this->previousPage(),
							'nextPage' => $this->nextPage(),
							'totalPages' => $this->numberOfPages());
	}
}
