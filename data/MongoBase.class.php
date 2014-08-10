<?php
abstract class MongoBase extends MongoCollection
{
	use DataTypeSetter;
/**
 * Instance of the site.
 * @var site -
 */
	public $site;
/**
 * @var string This probably isn't necessary with mongo, but is kept to maintain compat with mysql base object.
 */
	public $primaryKey = '_id';
/**
 * Holds the class name and the list of lookup fields.
 * @var array
 * @example
 * <code>
 * protected $manyObjectRelationship = array('ClassName' => array('ourField' => 'objectsField', ...));
 * </code>
 */
	public $manyObjectRelationship = array();

/**
 * Associates an external class relationship by its name, to the foreign key in this instance.
 * @var array
 * @example
 * <code>
 * protected $objectRelationship =array('ClassName' => array('ourField' => 'objectsField', ...));
 * </code>
 */
	public $objectRelationship = array();

/**
 * The name of the 'main' table.
 * @var string
 */
	public $collection;

/**
 * Contains a list of expirable data. See Schema::expire(), set(), setExtended().
 * @var array
 */
	private $expireList = array();

/**
 * Holds the unique identifier for this instance/class used in lookups to the static data associated.
 * @var string
 */
	protected $registry;

/**
 * Keeps track of the state of fields, if a field was manually changed place in this array as an associative key.
 * @var array
 */
	protected $changed = array();

/**
 * Holds extended values in associative array, i.e. array('isSpiffyClass' => true).
 * @var array
 */
	protected $extended = array();

	/**
 * On a successful insert event.
 * @var array
 */
	protected $onAfterInsert = array();

/**
 * On before an insert event.
 * @var array
 */
	protected $onBeforeInsert = array();

/**
 * On successful update event.
 * @var array
 * @todo Fully implement the event/subscriber methods
 */
	protected $onAfterUpdate = array();

/**
 * Before database entry update event subscribers.
 * @var array
 */
	protected $onBeforeUpdate = array();

/**
 * On successful delete event.
 * @var array
 * @todo Fully implement the event/subscriber methods
 */
	protected $onAfterRemove = array();

/**
 * On before delete event.
 * @var array
 * @todo Fully implement the event/subscriber methods
 */
	protected $onBeforeRemove = array();
	/**
	 * Defines a custom function to do conversions on a field as it is retrieved.
	 *
	 * A multi-dimensional associative array which should be indexed by the field that is being retrieved, and an array of function(s)
	 * that will be passed the fields value, and is expected to
	 * @var array
	 * @example
	 * <code>
	 * $getField = array('serializedField' => 'unserializeMeFunc');
	 * protected function unserializeMeFunc($value)
	 * {
	 *		return unserialize($value);
	 * }
	 * </code>
	 */
	protected $getField = array();

	/**
	 * Counterpart to the getField method.
	 *
	 * This will convert the data being passed into the format that would be pushed into the database.
	 * @var array
	 */
	protected $setField = array();
/**
 * On load of a document into the object
 * @var array
 * @todo Fully implement the event/subscriber methods
 */
	protected $onLoad = array();

	/**
	 * @var bool Flag to do auto mongo field cleanup to avoid invalid field characters to get input.
	 */
	public $autoClean = false;

/**
 * Holds values of all database fields from real tables.
 * @var array
 */
	protected $schemaValues = array();

/**
 * Holds tables/fields/datatype data from the tables schema.
 * @var array
 */
	protected $schema = array();

	/**
	 * @var fields to be unset on the next database update.
	 */
	protected $schemaUnset = array();
/**
 * Counter for active instances for a given registry.
  *@var array
 */
	private $iC = array();
/**
 * Flag that monitors when an instance has been properly loaded.
 * @var bool
 */
	public $isLoaded = false;

/**
 * Childs class name.
 * @var string
 */
	public $__CLASS__ = '';

/**
 * @var array A list of required fields. Validates these fields are set on insertion or fails the insert operation.
 */
	public $requiredFields = array();
/**
 * Constructor. Loads schema into static arrays
 * @param object $site Instance of site class
 */
	function __construct($site, $options = array())
	{
		foreach($options as $f => $v)
		{
			$this->$f = $v;
		}
		$this->__CLASS__ = get_class($this);
		$this->site = $site;
		if(!$this->site->mongoDB) // check if we have one defined
		{
			$this->site->error->toss('No mongo DB object defined. Add the configuration options for your mongo server. [mongo]');
		}
		parent::__construct($this->site->mongoDB, $this->collection);
		$this->startup();
	}

	/**
	 * will apply a mongo $unset operation to the given field for this instance. Most call update() to apply the event.
	 * @param string $field The field to unset. For subdoc granularity can specify a subdoc field with the mongo format
	 */
	public function unsetField($field)
	{
		$fields = explode('.', $field);
		// hard to unset with an indeterminate array depth... hard coded for now(ever)
		switch(count($fields))
		{
			case 1:
				unset($this->schemaValues[$this->registry][$fields[0]]);
				break;
			case 2:
				unset($this->schemaValues[$this->registry][$fields[0]][$fields[1]]);
				break;
			case 3:
				unset($this->schemaValues[$this->registry][$fields[0]][$fields[1]][$fields[2]]);
				break;
			case 4:
				unset($this->schemaValues[$this->registry][$fields[0]][$fields[1]][$fields[2]][$fields[3]]);
				break;
			case 5:
				unset($this->schemaValues[$this->registry][$fields[0]][$fields[1]][$fields[2]][$fields[3]][$fields[4]]);
				break;
			case 6:
				unset($this->schemaValues[$this->registry][$fields[0]][$fields[1]][$fields[2]][$fields[3]][$fields[4]][$fields[5]]);
				break;
		}
		$this->schemaUnset[$this->registry][$field] = '';
	}

/**
 * onLoad event.
 */
	protected function onLoad()
	{
		foreach($this->onLoad as $func)
		{
			$this->$func();
		}
	}

/**
 * Function called on every instance construction to do any additional setup that may be needed per instance.
 * Note, the fields for the database are not yet loaded at this point.
 */
	protected function startup(){}

/**
 * This is a faster validation method to determine if a field exists in the schema.
 * @param string $test The field to validate
 * @return boolean True if field is in schema list, false otherwise
 */
	protected function isValidSchemaField($test)
	{
		return true;
	}

/**
 * Sets all the properties (ensures proper cast types).
 * @param array $set Associative array of fields => values
 * @param bool $overWrite Will overwrite any registered data for this particular instance
 * @return bool Returns true for a successful load, false if nothing was loaded
 */
	public function loadRow($set, $overWrite = false)
	{
		$this->isLoaded = true;
		// if we already exist, we need to decrement to move to the new registry...
		if(isset($this->iC[$this->registry]))
		{
			$this->iC[$this->registry]--;
		}
		// create new registry and...
		// ... are we adding another instance?
		if(isset($this->iC[$this->buildRegistry($set['_id']->__toString())]))
		{
			$this->iC[$this->registry]++;
		}
		else // new one...
		{
			$this->iC[$this->registry] = 1;
		}

		// check if loaded already
		if(isset($this->schemaValues[$this->registry]) === false ||
			$overWrite === true)
		{
			// creates array properties for new instance
			$this->expireList[$this->registry]        = isset($this->expireList[$this->registry])?        $this->expireList[$this->registry] :        array();
			$this->extended[$this->registry]          = isset($this->extended[$this->registry])?          $this->extended[$this->registry] :          array();
			$this->changed[$this->registry]           = isset($this->changed[$this->registry])?           $this->changed[$this->registry] :           array();
			// set vals
			$this->schemaValues[$this->registry] = $set;
			$this->schemaUnset[$this->registry] = array();
		}
		$this->onLoad();
		return $this->isLoaded;
	}

/**
 * Class specific, determine whether inserting a new unit is valid.
 * @return bool
 */
	protected function validateInsert()
	{
		return true;
	}

/**
 * Class specific, determine whether updating a unit is valid.
 * @return bool
 */
	protected function validateUpdate()
	{
		return true;
	}
	/**
	 * Inserts a new entry.
	 * @return mixed Returns insert id on a successful insert, false on a failure due to failed custom validation or an unkown insertion error.
	 * @todo Do we need safe/fsync? Maybe have a class setting.
	 * @todo make this work better with filenames/gridfs, rather than using php ram for file xfer.
	 */
	public function insert($data=null, array $options = array())
	{
		$options['w'] = 1; // get validation info back from mongo
		$success = false;
		if ($data !== null) // using mongos built in method of handling data, make sure we push it through our pipelines too
		{
			$data = array_merge($this->getDefaults(), $data);
			foreach($data as $k => $v)
			{
				$this->_set($k, $v);
			}
		}
		else // internal storage
		{
			$this->schemaValues[$this->registry] = array_merge($this->getDefaults(), $this->schemaValues[$this->registry]);
		}
		// ok we got our data, lets insert it...
		if($this->validateInsert())
		{
			// validate with req fields..
			foreach($this->requiredFields as $f)
			{
				if(isset($this->schemaValues[$this->registry][$f]) === false)
				{
					return false; // fail it!!! MIA field.
				}
			}
			foreach( $this->onBeforeInsert as $func )
			{
				$this->$func();
			}
			try {
				$response = parent::insert($this->schemaValues[$this->registry], $options);
			} catch(Exception $e)
			{
				$this->site->error->toss($e->getMessage(), E_USER_WARNING);
			}
			// successful query?
			if($response['err'] === null)
			{
				$success = true;
				$this->changed[$this->registry] = array();
			}
			else
			{
				$this->site->error->toss($response['err'], E_USER_WARNING);
			}
		}

		if($success)
		{
			$this->isLoaded = true;
			$this->_set('_id', $this->schemaValues[$this->registry]['_id']);
			$this->moveRegistry($this->get('_id')->__toString());

			foreach( $this->onAfterInsert as $func )
			{
				$this->$func();
			}
			return $success;
		}
		return false;
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
	protected function mongoDate($val)
	{
		if(is_string($val)
		   && trim($val) !== '')
		{
			if(is_numeric($val)) 	// timestamp?
				$val = (int)$val;
			else 							// date string...
				$val = strtotime($val);
			$val = new MongoDate($val);
		}
		else if(is_array($val)
				&& isset($val['sec']))
		{
			$val = new MongoDate($val['sec'], isset($val['usec']) ? $val['usec'] : 0);
		}
		return $val;
	}

	/**
	* Alias for remove
	*/
	public function destroy() { return $this->remove(); }
	/**
	 * Removed current object. Skips if nothing is loaded.
	 *
	 * @return Type    Description
	 * @todo use safe mode?
	 */
	public function remove($criteria=null, array $options=array())
	{
		$success = false;
		$options['w'] = 1;
		if ($criteria !== null)
		{
			// do a normal mongo remove
			$response = parent::remove($criteria, $options);
		}
		else if($this->get('_id') !== null)
		{
			foreach($this->onBeforeRemove as $func)
			{
				$this->$func();
			}
			$response = parent::remove(array('_id' => $this->get('_id')));
			foreach($this->onAfterRemove as $func)
			{
				$this->$func();
			}
		}
		if(isset($response['err']) === false)
		{
			$success = true;
			// clean current instance.
			$this->schemaValues[$this->registry] = array();
			$this->schemaUnset[$this->registry] = array();
			$this->changed[$this->registry] = array();
			$this->clear();
		}
		return $success;
	}

/**
 * Runs the custom code listeners in the 'onFieldChange' var.
 * @param string $field The field that changed
 */
	private function onFieldChange($field)
	{
		if(isset($this->onFieldChange[$field]))
		{
			foreach($this->onFieldChange[$field] as $function)
			{
				$this->$function($this->get($field));
			}
		}
	}

	/**
	 * Return an array of all the default fields
	 * @return array
	 */
	public function getDefaults()
	{
		return array();
	}

/**
 * Moves current registry and associated values to a new registry seed. Primarily used for insertion where a new primary key is created.
 * @param string $seed the value of the primary key
 * @return mixed the reqistry index or boolean false
 */
	protected function moveRegistry($seed)
	{
		$old_registry = $this->registry;
		// copy values to the new registry index
		if( $this->buildRegistry($seed) !== $old_registry )
		{
			if($old_registry !== null)
			{
				$this->schemaValues[$this->registry]      = $this->schemaValues[$old_registry];
				$this->expireList[$this->registry]        = $this->expireList[$old_registry];
				$this->extended[$this->registry]          = $this->extended[$old_registry];
				$this->changed[$this->registry]           = $this->changed[$old_registry];
				$this->schemaUnset[$this->registry] = $this->schemaUnset[$old_registry];
			}
			// moving to an already existing registry, increment
			if(isset($this->iC[$this->registry]))
			{
				$this->iC[$this->registry]++;
			}
			// else never was created (new instance), initialize the counter
			else
			{
				$this->iC[$this->registry] = 1;
			}
			// clean up old registry...
			if(isset($this->iC[$old_registry]))
			{
				// delete old values from the old registry index
				$this->iC[$old_registry]--;
				if($this->iC[$old_registry] < 1)
				{
					unset(
						$this->schemaValues[$old_registry],
						$this->expireList[$old_registry],
						$this->extended[$old_registry],
						$this->changed[$old_registry],
						$this->schemaUnset[$old_registry],
						$this->iC[$old_registry]
					);
				}
			}
		}
		// return new registry
		return $this->registry;
	}
	/**
	 * Gets a MySQL UUID via the 'uuid_short()' function call.
	 * @param string $dataType
	 * @return int A UUID integer
	 */
    protected function getUUID($dataType = false)
    {
		// only strings and big int can handle uuid short
		switch($dataType)
		{
			case 'int':
				return mt_rand();
			// all else use uniqid
			default:
				return uniqid('', true);
		}
    }
	/**
	 *Casts a field by the datatype specified.
	 *@param string $dataType
	 *@param mixed $val Value to be cast
	 *@return mixed The cast value
	 */
	protected function dbCast($dataType, $val)
	{
		switch( $dataType )
		{
			case 'int':
			case 'number':
				return (integer)$val;
			case 'float':
			case 'double':
				return (double)$val;
			case "bool":
			case 'boolean':
				return (bool)$val;
			case 'array':
				if(is_array($val))
				{
					return $val;
				}
				else
				{
					return array($val);
				}
			default:
				return $val;
		}
	}
/**
 * Filter input as it is set, able to organize filters via field name or datatype.
 * @param string $field Field name
 * @param mixed $value The value being set
 * @return mixed Must return the value after filtering, or values will be null. Casting is done prior to value being passed, so no casting will occur here.
 * @todo MOVE TO AN ARRAY? Avoid extra function call that requires a loop in certain situations....
 */
	protected function setField($field, $value)
	{
		if( isset($this->setField[$field]) === true )
		{
			if( is_array($this->setField[$field]) )
			{
				foreach( $this->setField[$field] as $func )
				{
					$value = $this->$func($value, $field);
				}
				return $value;
			}
			$func = $this->setField[$field];
			return $this->$func($value, $field);
		}
		return $value;
	}
	/**
	* attempts to merge sub doc with new vals
	*@param array $doc The new vals
	*@param string $field The field name of the sub doc
	*@return array The merged array
	*/
	public function setSubDoc($doc, $field)
	{
		if(!($curDoc = $this->get($field)))
			$curDoc = array();
		return array_merge($curDoc, $doc);
	}
/**
 * Unique instance identifier.
 * @param mixed $seed The specific unique ID for the instance. Should not be an ambiguous value or instances will criss cross.
 * @return string The identifier to be used. Also sets the value in the SchemaBase->registry property.
 */
	protected function buildRegistry($seed)
	{
		// if you change how this works, you will need to change areas that assume the format of CLASS.ID
		$this->registry = $this->__CLASS__.'.'.$seed;
		return $this->registry;
	}
	/**
	 * Loads an instance from the DB based on query. Note: This uses findOne, so don't plan on getting a list.
	 *
	 * @param mixed $query A mongo query array, or a string/MongoId if you just want to grab by PK.
	 * @param array $fields use this to select a subset of data.
	 * @return boolean    True if successful
	 * @todo - probably need to deal with primary keys here better.
	 * @todo Should this set defaults, or just on save?
	 * @todo gridFS implimentation not optimized for large files since it loads entirely into RAM,
	 * maybe move to getter, or use as a file resource (best);
	 */
	public function findOne($query = array(), $fields = array())
	{
		// checks if data is already loaded
		if(is_array($query) === false )
		{
			if(is_a($query, 'MongoId'))
			{
				$query = array('_id' => $query);
			}
			else if(is_string($query))
			{
				$query = array('_id' => new MongoId($query));
			}
			if(isset($query['_id']) && isset($this->schemaValues[$this->__CLASS__.'.'.$query['_id']->__toString()]))
			{
				$this->buildRegistry($query['_id']->__toString());
				$this->isLoaded = true;
				return $this->isLoaded;
			}
		} else {
			// any alias?
			$aliasId = lcfirst(get_class($this)).'Id';
			if(isset($query[$aliasId])) {
				$query['_id'] = $query[$aliasId];
				unset($query[$aliasId]);
			}
		}
		// pass args to where generator
		if($row = parent::findOne($query, $fields))
		{
			// sets all the properties (ensures proper cast types)
			if($this->loadRow($row))
			{
				return true;
			}
		}
		$this->clear(); // didn't find anything, make sure to clean up ourself
		return false;
	}
	/**
	 *
	 */
	public function find($query = NULL, $fields = NULL)
	{
		$args = func_get_args();
		// if we are passing a string, assume we want a mongoId... lazy people.
		if(isset($args[0])
		   && is_string($args[0]))
			$args[0] = array('_id' => new MongoId($args[0]));
		return call_user_func_array(array('parent', 'find'),$args);
	}

	/**
	 * similar to find, but returns a MongoCursorPlus object instead of mongocursor, which will return typed objects instead of arrays.
	 *
	 * @param array $filter filter
	 *
	 * @return object    MongoCursorPlus object.
	 */
	public function findObjects($query = array())
	{
		return new MongoBaseCursor($this->site, get_class($this), $this->site->mongoDB.'.'.$this->collection, $query);
	}

	/**
	 * Get the field value for the field(s) requested.
	 * PARAM string|void $fieldname,... Passing a string of the fieldname, will return the value for that field. If no arguments passed, it will return all the row data in an associative array
	 * @return mixed The value(s) for that row in the table
	 */
	public function get()
	{
		$defaults = $this->getDefaults();
		switch( func_num_args() )
		{
			case 0:
				// grab instance field
				if( $this->registry !== null && isset($this->schemaValues[$this->registry]) === true )
				{
					$vals = $this->schemaValues[$this->registry];
					if( count($this->getField) !== 0 )
					{
						foreach( $this->getField as $field => $funcs )
						{
							if( array_key_exists($field, $vals) === true )
							{
								if( is_array($funcs) )
								{
									foreach($funcs as $func)
									{
										$vals[$field] = $this->$func($vals[$field]);
									}
								}
								else
								{
									$vals[$field] = $this->$funcs($vals[$field]);
								}
							}
						}
						return $vals;
					}
					return $vals;
				}
				return array();
			case 1:
				$args = func_get_args();
				if( $this->registry === null )
				{
					return null;
				}
				$val = null;
				if( isset($this->schemaValues[$this->registry][$args[0]]) === true )
				{
					$val = $this->schemaValues[$this->registry][$args[0]];
				}
				else
				{
					return null;
				}
				if( array_key_exists($args[0], $this->getField) )
				{
					$funcs = $this->getField[$args[0]];
					if( is_array($funcs) )
					{
						foreach($funcs as $func)
						{
							$val = $this->$func($val);
						}
						return $val;
					}
					return $this->$funcs($val);
				}
				return $val;
			default:
				return null;
		}
	}
	/**
	 * Sets the local values of the objects database fields. This local sets do not update the database. See the Schema::update Schema::insert methods.
	 * PARAM string $fieldname OPTIONAL The name of the field your are setting
	 * PARAM mixed $value OPTIONAL The value for that field
	 * Second useage:
	 * PARAM array $values OPTIONAL An associative array of the field => values
	 * @return boolean
	 */
	public function set()
	{
		$args = func_get_args();
		switch(func_num_args())
		{
			case 1:
				$return = false;
				if(is_array($args[0]))
				{
					foreach($args[0] as $field => $value)
					{
						if($this->set($field, $value) === true)
						{
							$return = true;
						}
					}
				}
				return $return;
			case 2:
				// clean up values as they come in..
				if($this->autoClean === true)
				{
					$args[0] = $this->mongoCleanField($args[0]);
					if(is_array($args[1]))
						$args[1] = $this->mongoCleanField($args[1]);
					$args[1] = $this->mongoCleanVal($args[1]);
				}
				// grab old value for comparison below
				$oldval = null;
				if( isset($this->schemaValues[$this->registry][$args[0]]) )
				{
					$oldval = $this->schemaValues[$this->registry][$args[0]];
				}
				$this->_set($args[0], $args[1]);
				if( $oldval !== $this->schemaValues[$this->registry][$args[0]] )
				{
					// expire dependent systems
					$this->expire($args[0]);
					$this->changed[$this->registry][$args[0]] = $oldval;
					$this->onFieldChange($args[0]);
				}
				return true;
			default:
				return false;
		}
	}

	/**
	 *Recursively strips invalid mongo field characters.
	 *@param string|array $field The field( or subdocuments ) to clean up.
	 *@return string|array The cleaned results
	 */
	public function mongoCleanField($field)
	{
		if(is_array($field))
		{
			foreach($field as $i => $f)
			{
				if(is_numeric($i) === false)
				{
					$b = $this->mongoCleanField($i);
					if($b !== $i) // it was invalid, reset it
					{
						unset($field[$i]); // ditch old reference
						$i = $b;
						if(is_array($f))
							$field[$i] = $this->mongoCleanField($f);
						else
							$field[$i] = $f;
					}
				}
			}
			return $field;
		}
		else
		{
			return str_replace(array('.', '$'), '', $field);// invalid mongo field chars
		}
	}
	/**
	 *Cleans non-utf8 chars and attempts to convert to their utf-8 counterpart
	 *@param mixed $val The value to clean.
	 */
	public function mongoCleanVal($val)
	{
		if(is_array($val))
		{
			foreach($val as $i => $v)
			{
				$val[$i] = $this->mongoCleanVal($v);
			}
			return $val;
		}
		else if(is_string($val))
			return mb_convert_encoding( $val,'UTF-8');//mb_convert_encoding(preg_replace('/[\x00-\x1F\x80-\xFF]/', "", $val),'UTF-8');
		else
			return $val;
	}

/**
 * Primary setter function that updates properties.
 * Any logic for properties dependant on something should be added here.
 * @param string $prop property to update
 * @param mixed $val value
 */
	protected function _set($prop, $val)
	{
		// if not registered, build temp registry that gets updated on an insert
		if( $this->registry === null )
		{
			$this->buildRegistry(uniqid(microtime(), true));
			// creates array properties for new instance
			$this->schemaUnset[$this->registry]      = isset($this->schemaUnset[$this->registry])?      $this->schemaUnset[$this->registry] :      array();
			$this->schemaValues[$this->registry]      = isset($this->schemaValues[$this->registry])?      $this->schemaValues[$this->registry] :      array();
			$this->expireList[$this->registry]        = isset($this->expireList[$this->registry])?        $this->expireList[$this->registry] :        array();
			$this->extended[$this->registry]          = isset($this->extended[$this->registry])?          $this->extended[$this->registry] :          array();
			$this->changed[$this->registry]           = isset($this->changed[$this->registry])?           $this->changed[$this->registry] :           array();
			$this->iC[$this->registry]           = isset($this->iC[$this->registry])?           $this->iC[$this->registry] + 1 : 1;
		}
		if( isset($this->schema[$prop]) )
		{
			$this->schemaValues[$this->registry][$prop] = $this->dbCast($this->schema[$prop], $this->setField($prop, $val));
		}
		else
		{
			$this->schemaValues[$this->registry][$prop] = $this->setField($prop, $val);
		}
	}

	function __call($func, $args)
	{
		// get{Field} ?
		if(substr($func, 0, 3) === 'get') {
			$field = lcfirst(substr($func, 3));

			return $this->get($field);
		}

		throw new \Exception("Uncallable method: ".$func, E_USER_ERROR);
		return null;
	}
	/**
	 * Builds the object mapping through the magic getters.
	 * This should not be used directly
	 * @param mixed $field
	 * @return null
	 */
	function __get($field)
	{
		if(isset($this->extended[$this->registry][$field]))
		{
			return $this->extended[$this->registry][$field];
		}
		// grab related object
		if(isset($this->objectRelationship[$field]))
		{
			$dependency = array();
			foreach($this->objectRelationship[$field] as $className => $lookup)
			{
				$v = new $className($this->site);
				if(is_array($lookup))
				{
					$search = array();
					foreach($lookup as $a => $b)
					{
						if(is_array($b))
						{
							$dependency[] = $b[0];
							$search[] = array($b[0], $b[1], $this->get($b[2]));
						}
						else
						{
							$dependency[] = $b;
							$search[$a] = $this->get($b);
						}
					}
					$v->findOne($search);
				}
				else
				{
					$v->findOne($lookup);
				}
				return $this->setExtended($field, $v, $dependency);
			}
		}
		// grab many related objects
		if(isset($this->manyObjectRelationship[$field]))
		{
			foreach($this->manyObjectRelationship[$field] as $className => $lookup)
			{
				$dependency = array();
				$object = new $className($this->site);
				if(is_array($lookup))
				{
					$search = array();
					foreach($lookup as $a => $b)
					{
						if(is_array($b))
						{
							$dependency[] = $b[2];
							$search[] = array($b[0], $b[1], $this->get($b[2]));
						}
						else
						{
							$dependency[] = $a;
							$search[$b] = $this->get($a);
						}
					}
					return $this->setExtended($field, $object->find($search), $dependency);
				}
				else
				{
					return $this->setExtended($field, $object->find($lookup), $dependency);
				}
			}
		}

		// not a defined property
		return null;
	}

	/**
	 * Merges any number of arrays / parameters recursively, replacing
	 * entries with string keys with values from latter arrays.
	 * If the entry or the next value to be assigned is an array, then it
	 * automagically treats both arguments as an array.
	 * Numeric entries are appended, not replaced, but only if they are
	 * unique
	 *
	 * calling: result = array_merge_recursive_distinct(a1, a2, ... aN)
	 *
	 * @return array    resulting array is returned after merge.
	 */
	private function valueMerge()
	{
		$arrays = func_get_args();
		$base = array_shift($arrays);
		if (!is_array($base)) $base = empty($base) ? array() : array(
			$base
		);
		foreach ($arrays as $append) {
			if (!is_array($append)) $append = array(
				$append
			);
			foreach ($append as $key => $value) {
				if (!array_key_exists($key, $base) and !is_numeric($key)) {
					$base[$key] = $append[$key];
					continue;
				}
				if (is_array($value) || (array_key_exists($key, $base) && is_array($base[$key]))) {
					if(isset($base[$key]) === false)
					{
						$base[$key] = null;
					}
					$base[$key] = $this->valueMerge($base[$key], $append[$key]);
				} else if (is_numeric($key)) {
					if (!in_array($value, $base)) $base[] = $value;
				} else {
					$base[$key] = $value;
				}
			}
		}
		return $base;
	}

	/**
	 * Returns the previous value for a field.
	 * @param string $field The field to retrieve value for
	 * @return mixed The previous value
	 */
	public function getPreviousValue($field)
	{
		if( isset($this->changed[$this->registry][$field]) )
		{
			return $this->changed[$this->registry][$field];
		}
		return null;
	}
	/**
	 * inserts current object, using defaults if set.
	 *
	 * @return boolean    false if there's a problem
	 * @todo safe mode?
	 */
	public function update($criteria=null, $data=null, array $options=array())
	{
		$internalUpdate = false;
		$options['w'] = 1;
		if ($data === null
			&& $criteria === null
			&& is_a($this->get('_id'), 'MongoId') === true) // non specified, update with internal vals
		{
			$internalUpdate = true; // flag for below
			$criteria = array('_id' => $this->get('_id'));
			$data = array();
			if(count($this->changed[$this->registry]))
			{
				$f = array();
				foreach($this->changed[$this->registry] as $field => $oldval)
				{
					$f[$field] = $this->schemaValues[$this->registry][$field];
				}
				$data = array('$set' => $f);
			}
			if(count($this->schemaUnset[$this->registry]))
			{
				$data['$unset'] = $this->schemaUnset[$this->registry];
			}
			// nothing to update?
			if(count($data) === 0)
			{
				return true;
			}
		}
		if($internalUpdate)
		{
			if( $this->validateUpdate() === false )
			{
				return false;
			}
			foreach( $this->onBeforeUpdate as $func )
			{
				$this->$func();
			}
		}
		$r = parent::update($criteria, $data, $options);
		if($internalUpdate)
		{
			foreach( $this->onAfterUpdate as $func )
			{
				$this->$func();
			}
			$this->changed[$this->registry] = array();
			if($r['err'] === null)
			{
				return true;
			}
			else
			{
				$this->site->error->toss($r['err'], E_USER_WARNING);
				return false;
			}
		}
		if($r['err'] !== null)
		{
			$this->site->error->toss($r['err'], E_USER_WARNING);
		}
		return $r;
	}

	/**
	 *Remove the relationship this instance has to the currently loaded fields.
	 */
	public function clear()
	{
		$this->isLoaded = false;
		$this->registry = null;
	}
	/**
	 * Takes a field and expires all the 'extended' cache associated with that field.
	 * The list of 'extended' data to expire is stored in Object->expireList array.
	 * @param string $property The field to expire
	 */
	public function expire($property)
	{
		if(isset($this->expireList[$this->registry][$property]))
		{
			foreach($this->expireList[$this->registry][$property] as $func => $b)
			{
				unset($this->expireList[$this->registry][$property][$func], $this->extended[$this->registry][$func]);
			}
		}
		if(isset($this->expireList[$this->registry]['*']))
		{
			foreach($this->expireList[$this->registry]['*'] as $func => $b)
			{
				unset($this->expireList[$this->registry]['*'][$func], $this->extended[$this->registry][$func]);
			}
		}
	}
}
