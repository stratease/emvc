<?php
class MongoSubDocument
{
	use DataTypeSetter;
	protected $row = array();
	public $primaryKey = null;
	protected $setField = array();
	public $site = null;
	function __construct()
	{
		if(empty($this->primaryKey))
		{
			trigger_error("Must define the 'primaryKey' field!", E_USER_WARNING);
		}
		$args = func_get_args();
		if(is_object($args[0]))
		{
			$this->site = $args[0];
			$me = $args[1];
		}
		else
		{
			$me = $args[0];
			trigger_error("Deprecated useage! First param should be instance of the Site object.", E_USER_DEPRECATED);
		}
		$this->load($me);
	}

	protected function getDefaults()
	{
		return array();
	}
	public function load($row)
	{
		$row = array_merge($this->getDefaults(), $row);
		if(isset($row[$this->primaryKey]))
		{
			$row[$this->primaryKey] = $this->mongoId($row[$this->primaryKey]);
		}
		else
		{
			$row[$this->primaryKey] = new MongoId();
		}
		$this->set($row);
	}

	protected function mongoId($val)
	{
		if(is_string($val)
		   && trim($val) !== '')
		{
			$val = new MongoId($val);
		}
		return $val;
	}

	public function get()
	{
		$args = func_get_args();
		switch(count($args))
		{
			case 0:
				return $this->row;
			case 1:
				return isset($this->row[$args[0]]) ? $this->row[$args[0]] : null;
		}
	}

	public function set()
	{
		$args = func_get_args();
		switch(count($args))
		{
			case 1:
				foreach($args[0] as $f => $v)
				{
					$this->set($f, $v);
				}
				break;
			case 2:
				if(isset($this->setField[$args[0]]))
				{
					$str = $this->setField[$args[0]];
					// null is flag to NOT set this field... maybe masking a more complex operation
					if(($v = $this->$str($args[1])) !== null)
					{
						$this->row[$args[0]] = $v;
					}
				}
				else
				{
					$this->row[$args[0]] = $args[1];
				}
				break;
		}
	}
}
