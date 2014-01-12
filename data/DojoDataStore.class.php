<?php

class DojoDataStore
{
	public $site;
	public $identifier = 'id';
	public $label = 'name';
	public $identifierField = '_id';
	public $labelField = 'name';
	public $start = 0;
	public $count = 10;
	private $value = null;
	private $regex = '';
	private $searchParams = array();
	private $returnFields = array();
	function __construct($site, $data)
	{
		$this->site = $site;
		$this->data = $data;
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
			$this->value = $search[$this->identifier];
		}
		else
		{
			$this->regex = '/^'.str_replace('*', '.*', $search[$this->label]).'$/i';
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
		// empty search... blank out values?
		if(isset($this->postVars[$this->label]) === true
		   && $this->postVars[$this->label] === ''
		   && isset($this->postVars[$this->identifier]) === false)
		{
			echo json_encode(array('numRows' => 0, 'items' => array(), 'identifier' => $this->identifier, 'label' => $this->label));
		}
		else
		{
			// search for elements...
			$cnt = 0;
			$rows = array();
			if($this->value) // search for a specific one?
			{
				foreach($this->data as $row)
				{
					if(strtolower($row[$this->labelField]) == strtolower($this->value))
					{
						$rows[] = array($this->identifier => (string)$row[$this->identifierField], $this->label => $row[$this->labelField]);
						$cnt++;
						break;
					}
				}
			}
			else // regex search...
			{
				foreach($this->data as $row)
				{
					if(preg_match($this->regex, $row[$this->labelField]))
					{
						if($cnt >= $this->start &&
						   count($rows) < $this->count)
						{
							$rows[] = array($this->identifier => (string)$row[$this->identifierField], $this->label => $row[$this->labelField]);
						}
						$cnt++;
					}
				}
			}
			array_multisort($rows);
			echo json_encode(array('numRows' => $cnt, 'items' => $rows, 'identifier' => $this->identifier, 'label' => $this->label));
		}
	}

	public function sort($a, $b)
	{
		if(isset($a[$this->labelField]) && isset($b[$this->labelField]))
		{

		}
		else
		{
			return 0;
		}
	}
}
