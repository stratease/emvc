<?php
class SchemaQuery
{
	private $object = null;
	private $orderBy = null;
	private $groupBy = array();
	private $start = null;
	private $limit = null;
	private $where = array();
	private $select = array();
	private $query = null;
	function __construct($object)
	{
		$this->type = is_a($object, 'SchemaList') ? 'list' : null;
		$this->site = $object->site;
		$this->object = $object;
	}
	public function where()
	{
		$n = func_num_args();
		// index by numb of args, so we can parse optimally in buildWhere
		// are we set already? merge search args
		if(isset($this->where[$n]))
		{
			$this->where[$n] = array_merge($this->where[$n], func_get_args());
		}
		else
		{
			$this->where[$n] = func_get_args();
		}
		return $this;
	}
	public function groupBy($groupBy)
	{
		$this->groupBy[] = $groupBy;
		return $this;
	}
	public function orderBy($orderBy)
	{
		$this->orderBy = $orderBy;
		return $this;
	}
	public function limit($start, $limit = null)
	{
		$this->start = $start;
		$this->limit = $limit;
		return $this;
	}
	private function buildWhere()
	{
		$where = array();
		foreach($this->where as $numArgs => $args)
		{
			if($numArgs === 1)
			{
				if(is_array($args[0]))
				{
					foreach($args[0] as $a => $b)
					{
						if(is_array($b))
						{
							if(is_array($b[2]))
							{
								$where[] = "`".$b[0]."` ".$this->site->db->escape($b[1])." ('".implode("', '", $this->site->db->escape($b[2]))."')";
							}
							else
							{
								$where[] = "`".$b[0]."` ".$this->site->db->escape($b[1])." '".$this->site->db->escape($b[2])."'";
							}
						}
						else
						{
							$where[] = "`".$a."` = '".$this->site->db->escape($b)."'";
						}
					}
				}
				else // passing the where statement directly, should contain no trailing operators
				{
					$where[] = $args[0];
				}
			}
			elseif($numArgs === 2)
			{
				if(is_array($args[1]))
				{
					$where[] = "`".$args[0]."` IN ('".implode("', '", $this->site->db->escape($args[1]))."')";
				}
				else
				{
					$where[] = "`".$args[0]."` = '".$this->site->db->escape($args[1])."'";
				}
			}
			elseif(($numArgs % 3) === 0)
			{
				for($i = 0; $i < $numArgs; $i+=3)
				{
					if(is_array($args[$i+2]))
					{
						$where[] = "`".$args[$i]."` ".$this->site->db->escape($args[$i+1])." ('".implode("', '", $this->site->db->escape($args[$i+2]))."')";
					}
					else
					{
						$where[] = "`".$args[$i]."` ".$this->site->db->escape($args[$i+1])." '".$this->site->db->escape($args[$i+2])."'";
					}
				}
			}
		}
		if(count($where))
		{
			return implode(" AND ", $where);
		}
		else
		{
			return false; // no where statement..
		}
	}
	private function buildGroupBy()
	{
		return implode(",\n ", $this->groupBy);
	}
	/**
	 *@todo finish cleaning this up/ adding features
	 */
	public function select($q)
	{
		$this->select[] = $q;
		return $this;
	}
	private function buildSelect()
	{
		if(count($this->select))
		{
			return implode(",\n ", $this->select);
		}
		return '*';// default to all?
	}
	/**
	 * Runs the query being generated, then loads the object with the results of that query
	 * @return mixed The SchemaList or SchemaBase object loaded with the results of the query. You will have to do your own validation if  the object was loaded with expected results by checking the "isLoaded" property.
	 */
	public function run()
	{
		$this->object->clear();
		$q = $this->getQuery();
		$results = $this->site->db->query($q);
		switch($this->type)
		{
			case 'list':
				$class = $this->object->__CLASS__;
				while($row = $results->fetch_assoc())
				{
					$o = new $class($this->site);
					if($o->loadRow($row))
					{
						$this->object->push($o);
					}
				}
				break;
			default:
				if($row = $results->fetch_assoc())
				{
					$this->object->loadRow($row);
				}
				break;
		}
		return $this->object;
	}
	/**
	 *Retrieves the interpreted query string. Useful if you don't want to auto load the Schema object or you are doing aggregate queries.
	 *@return string The query string
	 */
	public function getQuery()
	{
		if($this->query !== null) // specific query given...
		{
			$q = $this->query;
		}
		else // dynamic query..
		{
			$q = "SELECT ".$this->buildSelect()."
					FROM ".$this->object->buildFrom();
			if($w = $this->buildWhere())
			{
				$q .= " WHERE ".$w;
			}
			if($g = $this->buildGroupBy())
			{
				$q .= " GROUP BY ".$g;
			}
			if($this->orderBy)
			{
				$q .= " ORDER BY ".$this->orderBy;
			}
			if($this->start !== null)
			{
				$q .= " LIMIT ".$this->start;
				if($this->limit !== null)
				{
					$q .= ", ".$this->limit;
				}
			}
		}
		return $q;
	}

	/**
	 * pass a specific query to be run by the associated Schema object.
	 * **WARNING you are responsible for all database escaping here.
	 *@param string $query The query string
	 */
	public function setQuery($query)
	{
		$this->query = $query;
		return $this;
	}
}
