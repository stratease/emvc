<?php
class SchemaList extends ArrayIterator
{
	/**
	 * Is loaded boolean.
	 * @var bool
	 */
	public $isLoaded = false;
	/**
	 * Eelement array.
	 * @var array
	 */
	private $elements = array();
	/**
	 * Key association array.
	 * @var array
	 */
	private $keyAssocation = array();
	/**
	 * Index integer.
	 * @var int
	 */
	private $i = 0;
	/**
	 * Count integer.
	 * @var int
	 */
	private $count = 0;
	/**
	 * Object variable.
	 * @var object
	 */
	private $obj;

	/**
	 * Requires an instance of the object type it will be using to map the iterator guts and searches.
	 * @param BaseUnit $obj The instance to be iterated on
	 */
	function __construct($obj)
	{
		$this->obj = $obj;
	}
	/**
	 * Returns the empty object that seeded the list
	 * @return Schema Base object
	 */
	public function getBaseObject()
	{
		return $this->obj;
	}
	/**
	 * Moves the internal pointer to the begging of the array.
	 */
	public function rewind()
	{
		$this->i = 0;
		if(count($this->elements))
		{
			return $this->elements[$this->i];
		}
	}
	/**
	 * Returns the current item.
	 * @return mixed
	 */
	public function current()
	{
		return $this->elements[$this->i];
	}

/**
 * Returns the index of the current location.
 * @return int
 */
	public function key()
	{
		return $this->elements[$this->i]->get($this->obj->primaryKey);
	}

/**
 * Moves the pointer to the next item.
 */
	public function next()
	{
		$this->i++;
		if( isset($this->elements[$this->i]) )
		{
			return $this->elements[$this->i];
		}
		return null;
	}
	/**
	 * If the current iteration exists.
	 * @return bool
	 */
	public function valid()
	{
		return isset($this->elements[$this->i]);
	}
	/**
	 * Counts how many items are in the list.
	 * @return int
	 */
	public function count()
	{
		return $this->count;
	}

/**
 * Check if the index is valid.
 * @param mixed $index
 * @return bool
 */
	public function offsetExists($index)
	{
		return isset($this->elements[$this->keyAssocation[$index]]);
	}

/**
 * Gets an object at a particular index.
 * @param mixed $index
 * @return object
 */
	public function offsetGet($index)
	{
		return $this->elements[$this->keyAssocation[$index]];
	}

/**
 * Pushes an object at a particular index
 * @param mixed $index
 * @param object $object
 */
	public function offsetSet($index, $object)
	{
		if( $index === null )
		{
			$index = $object->get($this->obj->primaryKey);
		}

		if( !isset($this->keyAssocation[$index]) )
		{
			$this->keyAssocation[$index] = $this->count++;
		}

		$this->elements[$this->keyAssocation[$index]] = $object;
	}

/**
 * Seeks to the position specified.
 * @param mixed $position
 */
	public function seek($position)
	{
		$this->i = $position;
		return $this->elements[$this->i];
	}

/**
 * Unsets at a particular index.
 * @param mixed $index
 */
	public function offsetUnset($index)
	{
		$this->count--;
		unset($this->elements[$this->keyAssocation[$index]]);
		unset($this->keyAssocation[$index]);
		if( $index == $this->i )
		{
			$this->i--;
		}
		return $this->elements[$this->i];
	}

/**
 * Alias for push
 * @param object $object
 */
	public function append($object)
	{
		$this->push($object);
	}

/**
 * Push a loaded object on the list
 * @param object $obj
 */
	public function push($obj)
	{
		$this->isLoaded = true;
		$this->keyAssocation[$obj->get($this->obj->primaryKey)] = $this->count++;
		$this->elements[] = $obj;
	}
	/**
	 *Clears this list object pointers to any set of results
	 */
	public function clear()
	{
		$this->isLoaded = false;
		$this->elements = array();
		$this->keyAssocation = array();
		$this->i = 0;
		$this->count = 0;
	}
	/**
	 * Retrieves an individual instance on a field : value specified.
	 * PARAM string $field OPTIONAL The field name
	 * PARAM mixed $value OPTIONAL The value of the field. Treated as a string.
	 * PARAM mixed $more OPTIONAL The value of the field. Treated as a string.
	 * @return bool True if entry is found and loaded, false otherwise
	 */
	public function select()
	{
		$this->clear();
		$where = null;
		// no args, so I'm searching for ALL... or doing a specific search
		if(func_num_args() === 0
		   || ($where = call_user_func_array(array($this->obj, 'buildWhere'), func_get_args())))
		{
			$query = 'SELECT *
				FROM '.$this->obj->buildFrom();
			// pass args to where generator
			if($where)
			{
				$query .= ' WHERE '.$where;
			}
			$results = $this->site->db->query($query);
			$success = ($results->num_rows > 0);
			while($row = $results->fetch_assoc())
			{
				$obj = new $this->obj->__CLASS__($this->obj->site);
				if($obj->loadRow($row))
				{
					$this->push($obj);
				}
			}
			return $success;
		}
		return false;
	}
	/**
	 * Magic method call function
	 * @param mixed $method
	 * @param mixed $arguments
	 * @return mixed
	 */
	function __call($method, $arguments)
	{
		switch( $method )
		{
			case 'buildFrom':
				return $this->obj->buildFrom();
			default:
				$key = $this->obj->primaryKey;
				$return = array();
				foreach( $this->elements as $object )
				{
					$return[$object->get($key)] = call_user_func_array(array($object, $method), $arguments);
				}
				return $return;
		}
	}

/**
 * Magic getter
 * @param mixed $property
 * @return mixed
 */
	function __get($property)
	{
		switch( $property )
		{
			case '__CLASS__':
				return $this->obj->__CLASS__;

			case 'primaryKey':
				return $this->obj->primaryKey;

			case 'site':
				return $this->obj->site;

			default:
				$key = $this->obj->primaryKey;
				$return = array();
				foreach( $this->elements as $object )
				{
					$return[$object->get($key)] = $object->$property;
				}
				return $return;
		}
	}

/**
 * Magic setter
 * @param mixed $property
 * @param mixed $value
 */
	function __set($property, $value)
	{
		foreach( $this->elements as $key => $object )
		{
			$object->$property = $value;
		}
	}

/**
 * Sorts the list in reverse index order.
 */
	public function krsort()
	{
		krsort($this->keyAssocation);
		$i = 0;
		$e = $this->elements;
		foreach( $this->keyAssocation as $id => $v )
		{
			$e[$i++] = $this->elements[$v];
		}
		$this->elements = $e;
		return $this;
	}

/**
 * Sorts the list in the index order.
 */
	public function ksort()
	{
		ksort($this->keyAssocation);
		$i = 0;
		$e = $this->elements;
		foreach( $this->keyAssocation as $id => $v )
		{
			$e[$i++] = $this->elements[$v];
		}
		$this->elements = $e;
		return $this;
	}

/**
 * Pop object off the end of the array.
 * @return mixed
 */
	public function pop()
	{
		$this->count--;
		$last = array_pop($this->elements);
		$this->rewind();
		return $last;
	}

/**
 * Shift object off the beginning of the array.
 * @return mixed
 */
	public function shift()
	{
		$this->count--;
		$start = array_shift($this->elements);
		$this->rewind();
		return $start;
	}

	public function query()
	{
		$query = new SchemaQuery($this);
		if($w = call_user_func_array(array($this->obj, 'buildWhere'), func_get_args()))
		{
			$query->where($w);
		}
		return $query;
	}
}
