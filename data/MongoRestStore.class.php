<?php
class MongoRestStore extends RestStore
{
	public $targetObj = null;
	public $fields = array();
	protected $sortAsc = 1;
	protected $sortDesc = -1;
	/**
	* @param MongoBase $obj The object to interact with for this store
	* @param array $options The mongo/rest options
	*/
	function __construct($obj, $options = array())
	{
		if(!is_a($obj, 'Site'))
		{
			$this->targetObj = $obj;
			$this->site = $obj->site;
		}
		else
		{
			$this->site = $obj;
		}
		// customer getter
		$this->fetch = array($this, 'get');
		// set options...
		foreach($options as $f => $v)
		{
			$this->$f = $v;
		}
		$this->readHeaders();
	}
	protected function getPSearchFields($prefix, &$query)
	{
		$srch = array();
		foreach($query as $f => $val)
		{
			// prefixed field?
			if(substr($f, 0, strlen($prefix) ) === $prefix)
			{
				// custom search formatter?
				if(isset($this->searchFieldFormatter))
				{
					if(isset($this->searchFieldFormatter[$f]))
					{
						$srch = array_merge($srch, call_user_func_array($this->searchFieldFormatter[$f], array($f, $val)));
					}
				}
				else
					$srch[substr($f, strlen($prefix) )] = $val;
				unset($query[$f]); // peel it off
			}
		}
		if(count($srch))
			return $srch;
		else
			return false;
	}
	protected function getPSortField($prefix, &$sort)
	{
		$srch = array();
		if(is_array($sort))
		{
			foreach($sort as $k => $val)
			{
				// prefixed field?
				if(substr($k, 0, strlen($prefix)) === $prefix)
				{
					$srch[substr($k, strlen($prefix))] = $val;
					unset($sort[$k]); // peel it off
				}
			}
		}
		if(count($srch))
			return $srch;
		else
			return false;
	}

	public function addSearch($q)
	{
		$this->query = array_merge($this->query, $q);
	}
	/**
	 * function that does the actual search from the get http request.
	 * It is also responsible to set the totalCount property.
	 * @return array The results array to be output
	 */
	protected function get()
	{
		// search..
		// custom search hook
		if(isset($this->filterSearch))
		{
			$q = call_user_func_array($this->filterSearch, array($this->query));
		}
		else
		{
			$q = $this->query;
		}
		// check for custom sort hooks
		if(isset($this->filterSort))
		{
			$s = call_user_func_array($this->filterSort, array($this->sort));
		}
		else
		{
			$s = $this->sort;
		}
		// complex w joins ?
		if(isset($this->joins))
		{
			$tSearch = array();
			$sortO = false;
			// retain original properties as we cycle through the separate objects' fields

			// get sort and search fields for join objs
			foreach($this->joins as $srchDef)
			{
				if(isset($srchDef['fields']))
					$fields = $srchDef['fields'];
				else
					$fields = array();
				$cursor = null;
				// parse the search fields...
				if($sFields = $this->getPSearchFields($srchDef['prefix'], $q))
				{
					$obj = $srchDef['object'];
					$cursor = $obj->find($sFields,array('_id' => 1));
				}
				if($oField = $this->getPSortField($srchDef['prefix'], $s))
				{
					if($cursor === null)
						$cursor = $obj->find(array(), $fields);
					$cursor = $cursor->sort($oField);
					// store cursor and srch def for main loop below...
					$sortO = array('c' => $cursor, 'def' => $srchDef);
				}
				// sort and/or search ?
				if($cursor !== null)
				{
					foreach($cursor as $row)
					{
						$tSearch[$srchDef['id']]['$in'][] = $row['_id'];
					}
				}
			}

			if(is_array($q))
			{
				$srch = array();
				foreach($q as $f => $val)
				{
					// custom search formatter?
					if(isset($this->searchFieldFormatter))
					{
						if(isset($this->searchFieldFormatter[$f]))
						{
							$srch = array_merge($srch, call_user_func_array($this->searchFieldFormatter[$f], array($f, $val)));
						}
					}
				}
				$tSearch = array_merge($tSearch, $srch);
			}
			$start = $this->start;
			// end is an index location, limit takes a count
			if ($this->end !== null)
				$limit = ($this->end - $start);
			 // how many we fetching?
			else
				$limit = null;

			$results = array();
			// sort by other collection? loop on them...
			if($sortO !== false)
			{
				// get cnt first, since the sort will skew the main collection count
				$this->totalCount = $this->targetObj->find($tSearch, $this->fields)->count();
				foreach($sortO['c'] as $sRow) // loop on sorted collection
				{
					$tSearch[$sortO['def']['id']] = $sRow['_id'];
					$cursor = $this->targetObj->find($tSearch, $this->fields)->skip($start);
					$start -= $cursor->count();
					if ($start < 0)
						$start = 0;
					if ($limit !== null)
						$cursor->limit($limit);
					foreach($cursor as $row)
					{
						if ($limit !== null)
							$limit--;
						// join other data...
						$data = array($row);
						foreach($this->joins as $srchDef)
						{
							$fields = (isset($srchDef['fields'])) ? $srchDef['fields'] : array();
							if($srchDef['object']->findOne(array('_id' => $row[$srchDef['id']]), $fields))
								$data[] = $srchDef['object']->get();
							else
								$data[] = array(); // default empty
						}
						$results[] = call_user_func_array($this->getter, $data);
					}
					//check if we are done
					if ($limit !== null
							&& $limit <= 0)
					{
						break;
					}
				}
			}
			else
			{
				$cursor = $this->targetObj->find($tSearch, $this->fields)->skip($start);
				if(is_array($s))
					$cursor->sort($s);
				$this->totalCount = $cursor->count();
				$start -= $cursor->count();
				if ($start < 0)
					$start = 0;
				if ($limit !== null)
					$cursor->limit($limit);
				foreach($cursor as $row)
				{
					// join other data...
					$data = array($row);
					foreach($this->joins as $srchDef)
					{
						$fields = (isset($srchDef['fields'])) ? $srchDef['fields'] : array();
						if($srchDef['object']->findOne(array('_id' => $row[$srchDef['id']]), $fields))
							$data[] = $srchDef['object']->get();
						else
							$data[] = array(); // default empty
					}
					$results[] = call_user_func_array($this->getter, $data);
				}
			}
			return $results;
		}
		else // easy
		{
			$cursor = $this->targetObj->find($q, $this->fields)->skip($this->start)->limit($this->end - $this->start);
			if(is_array($s)) // if we have a sort defined
				$cursor->sort($s);
			// we find any?
			if($this->totalCount = $cursor->count())
			{
				foreach($cursor as $row)
				{
					// do we have custom formatter?
					if(isset($this->getter))
						$row = call_user_func_array($this->getter, array($row));
					else
						$row['_id'] = (string)$row['_id']; // stringify it for ease of use...
					$results[] = $row;
				}
				return $results;
			}
		}
		return array();
	}
	/**
	 * Responsible for delegating the actual creation of a database object off the passed fields, and returning the results.
	 * @param array $fields The data to insert
	 * @return array The results
	 */
	protected function create($fields)
	{
		$res = array();
		$obj = $this->targetObj;
		if(isset($this->createRow))
			$obj->set(call_user_func_array($this->createRow, array($fields)));
		else
			$obj->set($fields);
		if($obj->insert())
		{
			if(isset($this->getter))
			{
				$res = call_user_func_array($this->getter, array($obj->get()));
			}
			else
			{
				$res = $obj->get();
				$res['_id'] = (string)$res['_id'];
			}
		}
		return $res;
	}
	/**
	 * responsible for running the update on the object and returning the result
	 *@param string $id The identifier
	 *@param array $data The data to update in the item
	 *@return array The result
	 */
	protected function update($id, $data)
	{
		$out = array();
		$obj = $this->targetObj;
		if($obj->findOne($id))
		{
			if(isset($this->setter))
			{
				$data = call_user_func_array($this->setter, array($data));
			}
			$obj->set($data);
			$obj->update();
			$out = $obj->get();
			if(isset($this->getter))
				$out = call_user_func_array($this->getter, array($out));
			else
				$out['_id'] = (string)$out['_id']; // stringify it for ease of use...
		}
		return $out;
	}

	/**
	 * Responsible for retrieving the data for the specified id
	 * @param string $id The unique identifier
	 * @return array The result of the retrieval
	 */
	protected function retrieve($id)
	{
		$out = array();
		$obj = $this->targetObj;
		if($obj->findOne($id))
		{
			$out = $obj->get();
			if(isset($this->getter))
				$out = call_user_func_array($this->getter, array($out));
			else
				$out['_id'] = (string)$out['_id']; // stringify it for ease of use...
		}
		return $out;
	}
	/**
	 * Responsible for deleting the data for the specified id
	 * @param string $id The unique identifier
	 * @return bool The result of the retrieval
	 */
	protected function delete($id)
	{
		$out = array();
		$obj = $this->targetObj;
		if($obj->findOne($id))
		{
			return (bool)$obj->remove();
		}
		return false;
	}
}
