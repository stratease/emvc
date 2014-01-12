<?php
class DojoMongoDataStore
{
	public $site;
	public $identifier = 'id';
	public $label = 'name';
	public $identifierField = '_id';
	public $labelField = 'name';
	public $start = 0;
	public $count = 10;
	private $searchParams = array();
	private $returnFields = array();
	private $postVars = array();
	/**
	 *@var array The rows being appended to the main result set.
	 */
	private $rows = array('bottom' => array(),
									'top' => array());
	function __construct($site, $collection)
	{
		$this->site = $site;
		$this->collection = $collection;
	}


	/**
	 *Search method. This does actual look up and retrieval of the data. Does not output content directly
	 *@param array $search The associative post array
	 */
	public function postVars($search)
	{
		$this->postVars = $search;
		if(isset($search['start']))
		{
			$this->start = $search['start'];
		}
		if(isset($search['count']))
		{
			$this->count = $search['count'];
		}
		if(isset($search[$this->identifier]))
		{
			$srch = array($this->identifierField => new MongoId($search[$this->identifier]));
		}
		else
		{
			$srch = array($this->labelField => array('$regex' => str_replace('*', '', preg_quote($search[$this->label])).'.*', '$options' => 'i'));
		}
		$this->addSearch($srch);
	}
	/**
	 * Adds a row to the result set
	 *@param mixed $id The identifier for this 'row'
	 *@param mixed $val The value for this 'row'
	 *@param string $placement The location this row should be appended to the main result set. Available options: 'top' and 'bottom'
	 */
	public function addRow($id, $val, $placement = 'bottom')
	{
		$this->rows[$placement][] = array($this->identifier => $id, $this->label => $val);
	}
	public function addSearch($mongoParams, $fields = null)
	{
		$this->searchParams = array_merge($this->searchParams, $mongoParams);
		if($fields !== null)
		{
			$this->returnFields = array_merge($this->returnFields, $fields);
		}
	}

	/**
	 * outputs json object results, and headers...
	 */
	public function output()
	{
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		// no post vars yet? grab 'em
		if(empty($this->postVars))
		{
			$this->postVars($this->site->request->getVars());
		}

		// empty search... blank out values?
		if(isset($this->postVars[$this->label]) === true
		   && $this->postVars[$this->label] === ''
		   && isset($this->postVars[$this->identifier]) === false)
		{
			echo json_encode(array('numRows' => 0, 'items' => array(), 'identifier' => $this->identifier, 'label' => $this->label));
		}
		else
		{
			$cnt = $this->collection->find($this->searchParams, $this->returnFields)->count() + count($this->rows['top']) + count($this->rows['bottom']);
			$cursor = $this->collection->find($this->searchParams, $this->returnFields)->skip($this->start);
			if($this->count !== 'Infinity') // if it's not forevermore, limit the sucka...
				$cursor->limit($this->count);
			$cursor->sort(array($this->labelField => 1));
			$rows = $this->rows['top'];
			foreach($cursor as $item)
			{
				$rows[] = array($this->identifier => (string)$item[$this->identifierField], $this->label => $item[$this->labelField]);
			}
			foreach($this->rows['bottom'] as $r)
			{
				$rows[] = $r;
			}
			echo json_encode(array('numRows' => $cnt,
												'items' => $rows,
												'identifier' => $this->identifier,
												'label' => $this->label));
		}
	}
}
