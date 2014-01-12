<?php
abstract class SchemaBase
{
/**
 * Instance of the site.
 * @var site -
 */
	public $site;

/**
 * On load event.
 *
 * This is fired when an associated database instance has been loaded. This includes being fired after a successful insert.
 * @var array
 */
	protected $onLoad = array();

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
	protected $onAfterDelete = array();

/**
 * On before delete event.
 * @var array
 * @todo Fully implement the event/subscriber methods
 */
	protected $onBeforeDelete = array();

/**
 * Field on change event subscribers.
 * @var array
 */
	protected $onFieldChange = array();

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
 * Called when retrieving a default value for the given field.
 *@var array
 */
	protected $getDefault = array();

/**
 * Contains a list of expirable data. See Schema::expire(), set(), setExtended().
 * @var array
 */
	private static $expireList = array();

/**
 * Associative array to hold most recent sql insert ID's.
 * @var array
 */
	public static $insertId = array();

/**
 * Flag that monitors when an instance has been properly loaded.
 * @var bool
 */
	public $isLoaded = false;

/**
 * Holds the class name and the list of lookup fields.
 * @var array
 * @example
 * <code>
 * protected $manyObjectRelationship = array('alias' => array('ClassName' => array('ourField' => 'objectsField', ...;
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
 * The name of the primary key.
 * @var string
 */
	public $primaryKey;

/**
 * The name of the 'main' table.
 * @var string
 */
	public $primaryTable;

/**
 * List of tables for this instance from the main table perspective.
 * @var datatype
 * @example
 * <code>
 *		protected $tables = array('primaryKey' => 'maintable',
 *					'foreignkey1' => 'othertable1',
 *					'foreignkey2' => 'othertable2');
 * </code>
 * An example of the tables relationships with the primary key for the main table, and the foreign keys for the additional tables
 */
	public $foreignKeyMapping = array(); // student_id => array(student, student_extrastuff)

/**
 * The tables and their primary keys for the other tables directly associated with this instance.
 * @var array
 * @example
 * <code>
 * protected $relatedTables = array('othertable1' => 'othertable1foreignKey',
 * 'othertable2' => 'othertable2foreignKey');
 * </code>
 * The other tables listed in an array with the table name as the key, and the primary key of that table as the value
 */
	public $tableJoinMapping = array();

/**
 * Holds the unique identifier for this instance/class used in lookups to the static data associated.
 * @var string
 */
	protected $registry;

/**
 * Keeps track of the state of fields, if a field was manually changed place in this array as an associative key.
 * @var array
 */
	protected static $changed = array();

/**
 * Keeps track of the state of fields, if a field was manually changed place in this array as an associative key.
 * @var array
 */
	protected static $extrafieldChanged = array();

/**
 * Holds extended values in associative array, i.e. array('isSpiffyClass' => true).
 * @var array
 */
	protected static $extended = array();

/**
 * Holds values of all database fields from real tables.
 * @var array
 */
	private static $schemaValues = array();

/**
 * Holds values of all database fields from real tables.
 * @var array
 */
	private static $extrafieldValues = array();

/**
 * Should this object use the extrafields system?
 * @var array
 */
	protected $useExtrafields = false;

/**
 * The name of the foreign key tying the primary key of this object to the extrafield table
 * @var string
 */
	protected $extrafieldPrimaryKey;

/**
 * The name of the external props table table.
 *@var string
 */
	protected $extrafieldTable;

/**
 * The name of the column storing the name of the extrafield
 * @var string
 */
	protected $extrafieldKeyField = "field";

/**
 * The name of the column storing the value of the extrafield
 * @var string
 */
	protected $extrafieldValueField = "value";

/**
 * Holds tables/fields/datatype data from the tables schema.
 * @var array
 */
	private static $schema = array();

/**
 * Holds default values for all fields in all tables associated.
 * @var array
 */
	private static $schemaDefault = array();

/**
 * Holds mysql's `extra` values for all fields in all tables associated (auto_increment, etc).
 * @var array
 */
	protected static $schemaExtra = array();

/**
 * Tie the unique names of the fields in the extrafields table to their primary keys (for updating)
 * @var array
 */
	private static $extrafieldIndexes = array();

/**
 * Holds type of index if any. Empty string if not a type of index.
 * @var array
 */
	protected static $schemaIndexType = array();

/**
 * Counter for active instances for a given registry.
  *@var array
 */
	private static $iC = array();

/**
 * Childs class name.
 * @var string
 */
	public $__CLASS__ = '';


/**
 * Constructor. Loads schema into static arrays
 * @param object $site Instance of site class
 */
	function __construct($site)
	{
		$this->__CLASS__ = get_class($this);
		$this->site = $site;

		$this->getSchema();
		$this->startup();
	}

/**
 * onLoad event.
 */
	protected function onLoad()
	{
		if( $this->useExtrafields )
		{
			$this->loadFromExternal();
		}
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
 * Frees resources
 * @todo validate how well this is working? not sure it was fully tested to clean up memory. build unit test?
 */
	function __destruct()
	{
// decrement and check if we should clear memory
		if( isset(self::$iC[$this->registry]) )
		{
			self::$iC[$this->registry]--;
			if( self::$iC[$this->registry] < 1 )
			{
				unset(
					self::$extrafieldChanged[$this->registry],
					self::$extrafieldValues[$this->registry],
					self::$schemaValues[$this->registry],
					self::$expireList[$this->registry],
					self::$extended[$this->registry],
					self::$insertId[$this->registry],
					self::$changed[$this->registry],
					self::$extrafieldIndexes[$this->registry],
					self::$iC[$this->registry]
				);
			}
		}
	}

	function __clone()
	{
		if( isset(self::$iC[$this->registry]) )
		{
			self::$iC[$this->registry]++;
		}
	}

/**
 * Retrieves associative array with schema data for child object.
 * @return array The associative array in the format array('table' => array('field' => 'datatype'), ...);
 */
	public function getSchema()
	{
		if( isset(self::$extended[__FUNCTION__.$this->__CLASS__]) === false )
		{
			self::$extended[__FUNCTION__.$this->__CLASS__] = array();
			$tables = $this->tableJoinMapping;
			$tables[$this->primaryTable] = $this->primaryKey;
			foreach( $tables as $table => $f )
			{
				if( isset(self::$schema[$table]) === false )
				{
// retrieve any data we are missing
					$q = 'DESCRIBE `'.$table.'`';
					if( $results = $this->site->db->query($q) )
					{
						while( $row = $results->fetch_assoc() )
						{
							if( $row['Type'] === "tinyint(1)" )
							{
								self::$schema[$table][$row['Field']] = $row['Type'];
							}
// if we are on a 32 bit system, we need to spoof bigint datatypes as a string
// otherwise PHP will truncate the integer data that exceeds it's max int range
							elseif( substr($row['Type'], 0, 6) === 'bigint' && $this->site->is_32bit() )
							{
								self::$schema[$table][$row['Field']] = 'varchar';
							}
							else
							{
								self::$schema[$table][$row['Field']] = preg_replace('/\((.*?)\)/', '', $row['Type']);
							}

							self::$schemaDefault[$table][$row['Field']] = $row['Default'];
							self::$schemaIndexType[$table][$row['Field']] = $row['Key'];
							self::$schemaExtra[$table][$row['Field']] = $row['Extra'];
						}
					}
				}
				self::$extended[__FUNCTION__.$this->__CLASS__][$table] = self::$schema[$table];
			}
		}
		return self::$extended[__FUNCTION__.$this->__CLASS__];
	}

/**
 * Sets an array used for an 'extended' property that are sometimes useful to store (similar to cacheing).
 * @example<code>$this->setExtended(__FUNCTION__, $data);</code>
 * @param string $func Function name value is returned from
 * @param mixed $value Value to store in array, can be anything
 * @param mixed [optional] $dependency The instance field or a list of fields that will expire this extended value
 */
	private function setExtended($func, $value, $dependency = false)
	{
		if( $dependency !== false )
		{
			if( is_array($dependency) )
			{
				foreach( $dependency as $f )
				{
					self::$expireList[$this->registry][$f][$func] = true;
				}
			}
			else
			{
				self::$expireList[$this->registry][$dependency][$func] = true;
			}
		}

		self::$extended[$this->registry][$func] = $value;
		return self::$extended[$this->registry][$func];
	}

/**
 * This is a faster validation method to determine if a field exists in the schema.
 * @param string $test The field to validate
 * @return boolean True if field is in schema list, false otherwise
 */
	protected function isValidSchemaField($test)
	{
		if( isset(self::$extended[$this->__CLASS__][__FUNCTION__]) === false )
		{
// merge fields into one array to avoid iterating repeatedly across the schema
			foreach( $this->getSchema() as $table => $fields )
			{
				foreach( $fields as $field => $datatype )
				{
					self::$extended[$this->__CLASS__][__FUNCTION__][$field] = true;
					self::$extended[$this->__CLASS__][__FUNCTION__][$table.'.'.$field] = true;
				}
			}
		}
		return isset(self::$extended[$this->__CLASS__][__FUNCTION__][$test]);
	}

/**
 * Sets all the properties (ensures proper cast types).
 * @param array $set Associative array of fields => values
 * @param bool $overWrite Will overwrite any registered data for this particular instance
 * @return bool Returns true for a successful load, false if nothing was loaded
 */
	public function loadRow($set, $overWrite = false)
	{
		if( !isset($set[$this->primaryKey]) )
		{
// couldn't find primary key for some reason...
			$this->site->error->toss('Instance must load with a primary key value.', E_USER_WARNING);
			return false;
		}
		$this->isLoaded = true;

		// if we already exist, we need to decrement to move to the new registry...
		if(isset(self::$iC[$this->registry]))
		{
			self::$iC[$this->registry]--;
		}
		// create new registry and...
		// ... are we adding another instance?
		if(isset(self::$iC[$this->buildRegistry($set[$this->primaryKey])]))
		{
			self::$iC[$this->registry]++;
		}
		else // new one...
		{
			self::$iC[$this->registry] = 1;
		}
// check if loaded already
		if(isset(self::$schemaValues[$this->registry]) === false ||
				 $overWrite === true)
		{
// creates array properties for new instance
			self::$extrafieldChanged[$this->registry] = isset(self::$extrafieldChanged[$this->registry])? self::$extrafieldChanged[$this->registry] : array();
			self::$extrafieldIndexes[$this->registry] = isset(self::$extrafieldIndexes[$this->registry])? self::$extrafieldIndexes[$this->registry] : array();
			self::$extrafieldValues[$this->registry]  = isset(self::$extrafieldValues[$this->registry])?  self::$extrafieldValues[$this->registry] :  array();
			self::$schemaValues[$this->registry]      = isset(self::$schemaValues[$this->registry])?      self::$schemaValues[$this->registry] :      array();
			self::$expireList[$this->registry]        = isset(self::$expireList[$this->registry])?        self::$expireList[$this->registry] :        array();
			self::$extended[$this->registry]          = isset(self::$extended[$this->registry])?          self::$extended[$this->registry] :          array();
			self::$insertId[$this->registry]          = isset(self::$insertId[$this->registry])?          self::$insertId[$this->registry] :          array();
			self::$changed[$this->registry]           = isset(self::$changed[$this->registry])?           self::$changed[$this->registry] :           array();

			$tables = $this->tableJoinMapping;
			$tables[$this->primaryTable] = $this->primaryKey;
			foreach( $set as $key => $val )
			{
				$found = false;
				foreach( $tables as $table => $k )
				{
					if( isset(self::$schema[$table][$key]) )
					{
						$found = true;
						self::$schemaValues[$this->registry][$key] = $this->dbCast(self::$schema[$table][$key], $val);
					}
				}
				if( $found === false )
				{
					if( $this->useExtrafields && $this->isValidExtrafield($key) )
					{
						self::$extrafieldValues[$this->registry][$key] = $val;
					}
				}
			}
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
 */
	public function insert()
	{
		if( $this->validateInsert() === false )
		{
			return false;
		}
		foreach( $this->onBeforeInsert as $func )
		{
			$this->$func();
		}
		if( $this->processInsert() === true )
		{
			foreach( $this->onAfterInsert as $func )
			{
				$this->$func();
			}
			self::$changed[$this->registry] = array();
			self::$extrafieldChanged[$this->registry] = array();
			return true;
		}
		return false;
	}

/**
 * Validate whether the specified deletion is permittable.
 * @param mixed $id
 * @return boolean Return true if it's allowed, false if it's not.
 */
	public function validateDelete()
	{
		return true;
	}

/**
 * Deletes the loaded instance, AND associated table rows.
 * WARNING
 * If alternate behavior is required, overwrite the method 'processDelete'.
 * @param mixed $id [optional] The primary key value of the entry to delete
 * @return bool True on successful deletion, false if an error occurred or the operation failed validation inside the method 'validateDelete'.
 */
	public function delete($id = null)
	{
		if($id === null)
		{
			$id = $this->get($this->primaryKey);
		}
		if($this->validateDelete($id) === false)
		{
			return false;
		}
		foreach($this->onBeforeDelete as $func)
		{
			$this->$func();
		}
		if($this->processDelete($id) === true)
		{
			foreach($this->onAfterDelete as $func)
			{
				$this->$func();
			}

			return true;
		}
		return false;
	}

/**
 * Any custom validation is done in this method, overwrite with required logic.
 * @param mixed $id
 * @return bool True on successful validation to continue with delete, false if failed and will return out of delete operation.
 */
	protected function processDelete($id)
	{
		$tables = $this->foreignKeyMapping;
		$tables[] = $this->primaryTable;

		$q = "DELETE ".implode(',', $tables)."
				FROM ".$this->buildFrom()."
				WHERE ".$this->primaryTable.".".$this->primaryKey." = '".$this->site->db->escape($id)."'";
		if( $this->site->db->query($q) && $this->site->db->affected_rows > 0 )
		{
			if( $this->useExtrafields )
			{
				$this->site->db->query(
					"DELETE FROM `%s` WHERE `%s` = '%s'",
					$this->extrafieldTable,$this->primaryKey,$id
				);
			}
			return true;
		}
		return false;
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
 * Runs the custom code listeners in the 'getDefault' var.
 * @param string $field The field that changed
 * @todo this should probably be updated to allow for the overwriteable method in addition to pulling the databases defined 'default' value.
 * @return mixed
 */
	private function getDefault($field)
	{
		if( isset($this->getDefault[$field]) === true )
		{
			$function = $this->getDefault[$field];
			return $this->$function();
		}
		return null;
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
				self::$extrafieldChanged[$this->registry] = self::$extrafieldChanged[$old_registry];
				self::$extrafieldIndexes[$this->registry] = self::$extrafieldIndexes[$old_registry];
				self::$extrafieldValues[$this->registry]  = self::$extrafieldValues[$old_registry];
				self::$schemaValues[$this->registry]      = self::$schemaValues[$old_registry];
				self::$expireList[$this->registry]        = self::$expireList[$old_registry];
				self::$extended[$this->registry]          = self::$extended[$old_registry];
				self::$insertId[$this->registry]          = self::$insertId[$old_registry];
				self::$changed[$this->registry]           = self::$changed[$old_registry];
			}
			// moving to an already existing registry, increment
			if(isset(self::$iC[$this->registry]))
			{
				self::$iC[$this->registry]++;
			}
			// else never was created (new instance), initialize the counter
			else
			{
				self::$iC[$this->registry] = 1;
			}
			// clean up old registry...
			if(isset(self::$iC[$old_registry]))
			{
				// delete old values from the old registry index
				self::$iC[$old_registry]--;
				if(self::$iC[$old_registry] < 1)
				{
					unset(
						self::$extrafieldChanged[$old_registry],
						self::$extrafieldIndexes[$old_registry],
						self::$extrafieldValues[$old_registry],
						self::$schemaValues[$old_registry],
						self::$expireList[$old_registry],
						self::$extended[$old_registry],
						self::$insertId[$old_registry],
						self::$changed[$old_registry],
						self::$iC[$old_registry]
					);
				}
			}
		}
		// return new registry
		return $this->registry;
	}
/**
 * Class specific, use whatever logic is required for inserting new unit into database, should return new ID or array of ID's if multiple insertions.
 * @return bool True on successful insertion, or bool false on no insertion.
 */
	protected function processInsert()
	{
		$return = false;
		$schema = $this->getSchema();

		// clear in case we are looping in some way...
		self::$insertId[$this->registry] = array();
		$updateOtherTables = array();
		// must resolve the table associations before building the default values for the insertions below....
		if(count($this->tableJoinMapping))
		{
			foreach($this->foreignKeyMapping as $key => $t)
			{
				if(is_array($t) === false)
				{
					$tables = array($t);
				}
				else
				{
					$tables = $t;
				}

				foreach($tables as $table)
				{
					// if manually setting a foreign key, determine if you are trying to point the table to an existing related table, or if you're simply hard setting the new id to be inserted
					if( isset(self::$changed[$this->registry]) && array_key_exists($key, self::$changed[$this->registry]) )
					{
						// check if entry exists...
						$query = "/* ".$this->__CLASS__.' '.__METHOD__." */
										SELECT *
										FROM `".$table."`
										WHERE `".$this->tableJoinMapping[$table]."` = '".$this->site->db->escape((string)self::$schemaValues[$this->registry][$key])."'
										LIMIT 1";

						$results = $this->site->db->query($query);
						// if changed foreign key now points to an existing entry, merge the data
						// else leave to be inserted below
						if($results->num_rows)
						{
							// update modified fields
							if($row = $results->fetch_assoc())
							{
								// remove the id, we don't want to update/change this
								unset($row[$key]);

								$updateOtherTables[$table] = '';
								// these fields should be updated for the related table since it already existing
								foreach($row as $field => $value)
								{
									// if we modified the field, we need to update the database accordingly
									if(array_key_exists($field, self::$changed[$this->registry]) === true)
									{
										$updateOtherTables[$table] .= '`'.$field.'` = "'.$this->site->db->escape((string)self::$schemaValues[$this->registry][$field]).'",';
									}
									// not modified, so use the value that exists in the db to consolidate differences
									else
									{
										$this->_set($field, $row[$field]);
									}
								}
							}
						}
					}
				}
			}
		}

		// now that we checked for related table foreign key/association changes...
		// we can get default values
		$this->setDefaults(true);
		// need tables pulled out for processing insertions vs. updates
		$tables = $this->tableJoinMapping;
		// if we had some fields changed with a newly associated existing row
		if(count($updateOtherTables))
		{
			foreach($updateOtherTables as $table => $q)
			{
				if($q !== '')
				{
					// remove from insertion array
					unset($tables[$table]);

					$query = "/* ".$this->__CLASS__.' '.__METHOD__." */
						UPDATE `".$table."`
							SET ".substr($q, 0, -1)."
						WHERE `".$this->tableJoinMapping[$table]."` = '".$this->site->db->escape((string)self::$schemaValues[$this->registry][$this->tableJoinMapping[$table]])."'";
					$this->site->db->query($query);
				}
			}
		}
		// these are the "related tables" to the primary
		foreach($tables as $table => $relatedKey)
		{
			// if we couldn't find a table to do an update to or we did find one, but we didn't do any update -  then do an insert
			if(isset($updateOtherTables[$table]) === false)
			{
				$q = '';
				foreach(self::$schema[$table] as $field => $dt)
				{
					$q .= '`'.$field.'` = "'.$this->site->db->escape((string)self::$schemaValues[$this->registry][$field]).'",';
				}

				if($q !== '')
				{
					$query = "/* ".$this->__CLASS__.' '.__METHOD__." */
						INSERT INTO `".$table."`
							SET ".substr($q, 0, -1);
					$this->site->db->query($query);
					if(self::$schemaExtra[$table][$relatedKey] === 'auto_increment')
					{
						$foreignKey = null;
						foreach($this->foreignKeyMapping as $fkey => $ftbl)
						{
							if($ftbl === $table)
							{
								$foreignKey = $fkey;
								break;
							}
						}
						self::$insertId[$this->registry][$relatedKey] = $this->site->db->insert_id;
						$this->_set($relatedKey, self::$insertId[$this->registry][$relatedKey]);
						if($foreignKey !== null)
						{
							$this->_set($foreignKey, self::$insertId[$this->registry][$relatedKey]);
						}
					}
					else
					{
						self::$insertId[$this->registry][$relatedKey] = self::$schemaValues[$this->registry][$relatedKey];
					}
				}
			}
		}
		// set of fields for primary insertion
		$fieldset = self::$schema[$this->primaryTable];
		// if we didn't manually set the auto incr primary key, remove it from the list of updated values
		if(array_key_exists($this->primaryKey, self::$changed[$this->registry]) === false && self::$schemaExtra[$this->primaryTable][$this->primaryKey] === 'auto_increment')
		{
			unset($fieldset[$this->primaryKey]);
		}
		$q = '';
		foreach($fieldset as $field => $dt)
		{
			$q .= '`'.$field.'` = "'.$this->site->db->escape((string)self::$schemaValues[$this->registry][$field]).'",';
		}
		// primary table insertion //
		if($q !== '')
		{
			$query = "/* ".$this->__CLASS__.' '.__METHOD__.' */
				INSERT INTO `'.$this->primaryTable.'`
					SET '.substr($q, 0, -1);
			//primary table, register instance vars...
			if($this->site->db->query($query))
			{
				// autoincrementer
				if(isset(self::$changed[$this->registry][$this->primaryKey]) === false && self::$schemaExtra[$this->primaryTable][$this->primaryKey] === 'auto_increment')
				{
					self::$insertId[$this->registry][$this->primaryKey] = $this->site->db->insert_id;
					// set primary key
					$this->_set($this->primaryKey, $this->site->db->insert_id);
				}
				// else assume it's a randomly generated id from setDefaults() function
				else
				{
					self::$insertId[$this->registry][$this->primaryKey] = self::$schemaValues[$this->registry][$this->primaryKey];
				}
				// move registry
				$this->moveRegistry(self::$schemaValues[$this->registry][$this->primaryKey]);
				$this->isLoaded = true;
				$return = true;
			}
		}
		// Process inserts
		if( count(self::$extrafieldValues[$this->registry]) )
		{
			$this->processInsertExtrafield(self::$extrafieldValues[$this->registry]);
		}
		if( $return === true )
		{
			$this->onLoad();
		}
		return $return;
	}

/**
 * SchemaBase has determined that these fields are extrafields, and need to be inserted into the database... make it so
 */
	protected function processInsertExtrafield( $field, $value = null )
	{
		if( is_array($field) )
		{
			foreach( $field as $key => $value )
			{
				$this->processInsertExtrafield($key,$value);
			}
			return;
		}
		if( $value === null )
		{
			return;
		}
		$this->site->db->query(
			"INSERT INTO `%s` SET
			`%s` = '%s',
			`%s` = '%s',
			`%s` = '%s'",
			$this->extrafieldTable,
			$this->primaryKey,$this->get($this->primaryKey),
			$this->extrafieldKeyField,$field,
			$this->extrafieldValueField,$value
		);
		self::$extrafieldIndexes[$this->registry][$field] = $this->site->db->insert_id;
	}
	/**
	 * Will set default values from information_schema.
	 * @param bool $doNotOverride Whether to override values that are already set or not
	 */
	public function setDefaults($doNotOverride = true)
	{
		$tables = $this->tableJoinMapping;
		$tables[$this->primaryTable] = $this->primaryKey;

		foreach($tables as $table => $key)
		{
			foreach(self::$schemaDefault[$table] as $field => $default)
			{
				// check if custom defined defaults
                if(($val = $this->getDefault($field)) !== null && (($doNotOverride === true && $this->get($field) === null) || $doNotOverride === false))
                {
                    $default = $val;
                }
				// generate the primary key if not set as an auto incrementer (assumes a UUID is being used) and a unique value is required
				elseif((self::$schemaIndexType[$table][$field] === 'PRI' || self::$schemaIndexType[$table][$field] === 'UNI') &&
							self::$schemaExtra[$table][$field] !== 'auto_increment' &&
							($doNotOverride === false || ($doNotOverride === true && array_key_exists($field, self::$changed[$this->registry]) === false)))
				{
					$default = $this->getUUID(self::$schema[$table][$field]);
				}
				// skip if do not override is specified and the value is already set ...
				// or if unique key (those should be specifically set somehow)...
				// or if an auto incrementer
				elseif(($doNotOverride === true && $this->get($field) !== null) || self::$schemaExtra[$table][$field] === 'auto_increment')
				{
					continue;
				}
				// grabs the value being used for the primary key, and pushes it into the insert id for registering this instance
				// force check against field ambiguity (table.field)
				elseif($this->get($field) !== null && $this->primaryTable.$this->primaryKey === $table.$field)
				{
					self::$insertId[$this->registry][$this->primaryKey] = $this->get($field);/*TODO*/ // ????????? is this done in the insert function?
				}
				elseif($default === 'CURRENT_TIMESTAMP')
				{
					$default = date('Y-m-d H:i:s');
				}

				$this->_set($field, $default);
			}
		}
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
			case 'smallint':
			case 'tinyint':
				return mt_rand();
			case 'varchar':
			case 'text':
			case 'tinyblob':
			case 'mediumblob':
			case 'longblob':
			case 'tinytext':
			case 'mediumtext':
			case 'longtext':
			case 'blob':
			case 'char':
			case 'bigint unsigned':
				$q = "SELECT uuid_short()";
				if( $row = $this->site->db->query($q)->fetch_assoc() )
				{
					return $row['uuid_short()'];
				}
			// all else use uniqid
			default:
				return uniqid('', true);
		}
    }
	/**
	 * WARNING: With regard to primary keys that are non auto incremental.
	 * ------------------------------------------------------------------------------------
	 * Currently it assumes you are attempting to create a UUID (provided a value for the key wasn't passed in the setter)
	 * In order to retrieve a UUID it goes about doing so via the below steps/requirements.
	 * If the field is a UNSIGNED BIG INT:
	 *		PHP must also support 64 bit integers (64 bit systems)
	 * All other field datatypes:
	 *		It generates a pseudo random unique id via mt_rand, up to 10 decimals in length
	 *	@param string $table The table the field is in
	 *	@param string $field The field we are generating the value for
	 *	@return mixed The random UUID, Based off the datatype of the field
	 *	@todo Add other datatypes, currently only supports "int", and assumes a default of length 10 if not bigint
	 */
	protected function getRandomUUID($table, $field)
	{
		// if it supports a 64 bit integer
		if(self::$schema[$table][$field] === 'bigint unsigned')
		{
			$maxLength = 19;
		}
		else
		{
			$maxLength = 10;
		}

		// support int(10)
		$unique = substr(mt_rand(1, mt_getrandmax()).mt_rand(1, mt_getrandmax()), 0, rand(3, $maxLength));
		$q = "SELECT COUNT(*) as count
				FROM `".$table."`
				WHERE `".$field."` = '".$unique."'";
		$results = $this->site->db->query($q);
		$row = $results->fetch_assoc();
		while( (int)$row['count'] !== 0 )
		{
			$unique = substr(mt_rand(1, mt_getrandmax()).mt_rand(1, mt_getrandmax()), 0, rand(3, $maxLength));
			$q = "SELECT COUNT(*) as count
				FROM `".$table."`
				WHERE `".$field."` = '".$unique."'";
			$results = $this->site->db->query($q);
			$row = $results->fetch_assoc();
		}
		return $unique;
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
			case 'mediumint':
			case 'bigint':
			case 'smallint':
			case 'tinyint':
			case 'int':
			case 'int unsigned':
			case 'mediumint unsigned':
			case 'bigint unsigned':
			case 'smallint unsigned':
			case 'tinyint unsigned':
				return (integer)$val;
			case 'float':
			case 'float unsigned':
				return (float)$val;
			case 'double':
			case 'double unsigned':
			case 'decimal':
				return (double)$val;
			case "tinyint(1)":
				return (bool)$val;
			default:
				return $val;
		}
	}
	/**
	 * Retrieve an insert id value for a field.
	 * To be used after an insert() method, and you must specify the field that you expect to have the auto incrementer value.
	 * @param string $field The field to retrieve an id for
	 * @return int|null The integer value or null if none is found
	 */
	public function insertId($field)
	{
		if( isset(self::$insertId[$this->registry][$field]) )
		{
			return self::$insertId[$this->registry][$field];
		}
		return null;
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
					$value = $this->$func($value);
				}
				return $value;
			}
			$func = $this->setField[$field];
			return $this->$func($value);
		}
		return $value;
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
	 * Retrieves an individual instance on a field : value specified.
	 * Accepts an array of field:value pairs as an "AND" statement.
	 * @param mixed $field OPTIONAL The field name
	 * @param mixed $value OPTIONAL The value of the field. Treated as a string.
	 * @return bool True if entry is found and loaded, false otherwise
	 */
	public function select()
	{
		$args = func_get_args();
		// checks if data is already loaded
		if(func_num_args() === 1
		   && is_array($args[0]) === false
		   && isset(self::$schemaValues[$this->__CLASS__.'.'.$args[0]]))
		{
			$this->moveRegistry(func_get_arg(0));
			$this->isLoaded = true;
			return $this->isLoaded;
		}
		// pass args to where generator
		if($where = call_user_func_array(array($this, 'buildWhere'), $args))
		{
			// build lookup
			$q = '
			SELECT *
			FROM '.$this->buildFrom().'
			WHERE '.$where.'
			LIMIT 1';
			if($results = $this->site->db->query($q))
			{
				if($results !== false && $results->num_rows !== 0)
				{
					// sets all the properties (ensures proper cast types)
					if($this->loadRow($results->fetch_assoc()))
					{
						return true;
					}
				}
			}
		}
		$this->clear(); // didn't find anything, make sure to clean up ourself
		return false;
	}

	/**
	 * Takes various search args and converts into the WHERE MySQL statement
	 * @param mixed $args Can take associative array, or field, val, ....
	 * @return mixed The WHERE statement or false if no valid search was detected
	 */
	public function buildWhere()
	{
		$numArgs = func_num_args();
		$args = func_get_args();
		$where = array();
		if($numArgs === 1)
		{
			if(is_array($args[0]))
			{
				foreach($args[0] as $a => $b)
				{
					if(is_array($b) && $this->isValidSchemaField($b[0]))
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
					elseif($this->isValidSchemaField($a))
					{
						$where[] = "`".$a."` = '".$this->site->db->escape($b)."'";
					}
				}
			}
			else
			{
				$where[] = "`".$this->primaryKey."` = '".$this->site->db->escape($args[0])."'";
			}
		}
		elseif($numArgs === 2 && $this->isValidSchemaField($args[0]))
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
				if($this->isValidSchemaField($args[$i]))
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
		return false;
	}

	/**
	 * Builds joins with the associated tables into a string.
	 * Can be overwritten to perform more advanced joins. The query being built is expecting this function to return everything between the FROM and WHERE clauses.
	 * @example
	 * <code>
	 * $query = "SELECT * FROM ".$object->buildFrom()." WHERE table.id = '1'";
	 * </code>
	 * @return string The MySQL table joins
	 */
	public function buildFrom()
	{
		$me = __FUNCTION__.$this->__CLASS__;
		if( isset(self::$extended[$me]) === false )
		{
			$tablesSQL = '`'.$this->primaryTable."`";
			foreach( $this->foreignKeyMapping as $field => $table )
			{
				if( is_array($table) )
				{
					foreach( $table as $t )
					{
						$tablesSQL .= '
							INNER JOIN '.$t.'
								ON '.$t.'.'.$this->tableJoinMapping[$t].' = '.$this->primaryTable.'.'.$field;
					}
				}
				else
				{
					$tablesSQL .= '
						INNER JOIN '.$table.'
							ON '.$table.'.'.$this->tableJoinMapping[$table].' = '.$this->primaryTable.'.'.$field;
				}
			}
			self::$extended[$me] = $tablesSQL;
		}
		return self::$extended[$me];
	}

/**
 * Get the field value for the field(s) requested.
 * PARAM string|void $fieldname,... Passing a string of the fieldname, will return the value for that field. If no arguments passed, it will return all the row data in an associative array
 * @return mixed The value(s) for that row in the table
 */
	public function get()
	{
		switch( func_num_args() )
		{
			case 0:
				// grab instance field
				if( $this->registry !== null && isset(self::$schemaValues[$this->registry]) === true )
				{
					$vals = array_merge(self::$extrafieldValues[$this->registry],self::$schemaValues[$this->registry]);
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
				if( isset(self::$schemaValues[$this->registry][$args[0]]) === true )
				{
					$val = self::$schemaValues[$this->registry][$args[0]];
				}
				elseif( isset(self::$extrafieldValues[$this->registry][$args[0]]) === true )
				{
					$val = self::$extrafieldValues[$this->registry][$args[0]];
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
	 * @return boolean|null
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
				// grab old value for comparison below
				$oldval = null;
				if( isset(self::$schemaValues[$this->registry][$args[0]]) )
				{
					$oldval = self::$schemaValues[$this->registry][$args[0]];
				}
				elseif( isset(self::$extrafieldValues[$this->registry][$args[0]]) )
				{
					$oldval = self::$extrafieldValues[$this->registry][$args[0]];
				}
// Note: this is 1 underscore.. and IS NOT the php magic setter (which is 2 underscores)
// (many headaches later)
				if( $this->_set($args[0], $args[1]) === true )
				{
// check if value actually changed
					if( $this->isValidSchemaField($args[0]) )
					{
						if( $oldval !== self::$schemaValues[$this->registry][$args[0]] )
						{
// expire dependent systems
							$this->expire($args[0]);
							self::$changed[$this->registry][$args[0]] = $oldval;
							$this->onFieldChange($args[0]);
						}
					}
					elseif( $oldval !== self::$extrafieldValues[$this->registry][$args[0]] )
					{
// expire dependent systems
						$this->expire($args[0]);
						self::$extrafieldChanged[$this->registry][$args[0]] = $oldval;
						$this->onFieldChange($args[0]);
					}
					return true;
				}
				return false;

			default:
				return null;
		}
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
			self::$extrafieldChanged[$this->registry] = isset(self::$extrafieldChanged[$this->registry])? self::$extrafieldChanged[$this->registry] : array();
			self::$extrafieldIndexes[$this->registry] = isset(self::$extrafieldIndexes[$this->registry])? self::$extrafieldIndexes[$this->registry] : array();
			self::$extrafieldValues[$this->registry]  = isset(self::$extrafieldValues[$this->registry])?  self::$extrafieldValues[$this->registry] :  array();
			self::$schemaValues[$this->registry]      = isset(self::$schemaValues[$this->registry])?      self::$schemaValues[$this->registry] :      array();
			self::$expireList[$this->registry]        = isset(self::$expireList[$this->registry])?        self::$expireList[$this->registry] :        array();
			self::$extended[$this->registry]          = isset(self::$extended[$this->registry])?          self::$extended[$this->registry] :          array();
			self::$insertId[$this->registry]          = isset(self::$insertId[$this->registry])?          self::$insertId[$this->registry] :          array();
			self::$changed[$this->registry]           = isset(self::$changed[$this->registry])?           self::$changed[$this->registry] :           array();
			self::$iC[$this->registry]           = isset(self::$iC[$this->registry])?           self::$iC[$this->registry] + 1 : 1;
		}
		$tables = $this->tableJoinMapping;
		$tables[$this->primaryTable] = $this->primaryKey;

		foreach( $tables as $table => $k )
		{
			if( isset(self::$schema[$table][$prop]) )
			{
				self::$schemaValues[$this->registry][$prop] = $this->dbCast(self::$schema[$table][$prop], $this->setField($prop, $val));
				return true;
			}
		}
		return false;
	}


	/**
	 * Builds the object mapping through the magic getters.
	 * This should not be used directly
	 * @param mixed $field
	 * @return null
	 */
	function __get($field)
	{
		if(isset(self::$extended[$this->registry][$field]))
		{
			return self::$extended[$this->registry][$field];
		}
		// grab related object
		if(isset($this->objectRelationship[$field]))
		{
			foreach($this->objectRelationship[$field] as $className => $lookup)
			{
				$v = new $className($this->site);
				$dependency = array();
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
					$v->select($search);
				}
				else
				{
					if($results = $this->site->db->query($this->parseObjectQuery($lookup, $dependency)))
					{
						if($row = $results->fetch_assoc())
						{
							$v->loadRow($row);
						}
					}
				}
				return $this->setExtended($field, $v, $dependency);
			}
		}
		// grab many related objects
		if(isset($this->manyObjectRelationship[$field]))
		{
			foreach($this->manyObjectRelationship[$field] as $className => $lookup)
			{
				$object = new $className($this->site);
				if(is_array($lookup))
				{
					$search = array();
					$dependency = array();
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
					return $this->setExtended($field, $object->selectList($search), $dependency);
				}
				else
				{
					$list = new SchemaList($object);
					$dependency = array();
					if($results = $this->site->db->query($this->parseObjectQuery($lookup, $dependency)))
					{
						while($row = $results->fetch_assoc())
						{
							$o = new $className($this->site);
							if($o->loadRow($row))
							{
								$list->push($o);
							}
						}
					}
					return $this->setExtended($field, $list, $dependency);
				}
			}
		}
		// not a defined property
		return null;
	}
	/**
	 * Parses a query string searching for fields in the format {fieldname} and replacing them with the value for that field.
	 * @param string $lookup The query string
	 * @param array $dependency OPTIONAL The array of dependencies generated after parsing the string. To be used with the expirelist feature
	 * @return string The
	 */
	protected function parseObjectQuery($lookup, &$dependency = array())
	{
		// remove this-> ... it's assumed from henceforthwithbelow etc.
		$lookup = preg_replace("/([^a-zA-Z0-9].?this->)/", '', $lookup);

		while(preg_match("/{(.*?)}/", $lookup, $m) === 1)
		{
			if(preg_match("/([0-9a-zA-Z\.].*)\-\>([0-9a-zA-Z\.].*)/", $m[1], $ma) === 1)
			{
				$lookup = str_replace($m[0], $this->$ma[1]->get($ma[2]), $lookup);
			}
			else
			{
				$dependency[] = $m[1];
				$lookup = str_replace($m[0], $this->get($m[1]), $lookup);
			}
		}
		return $lookup;
	}
	/**
	 * Returns the previous value for a field.
	 * @param string $field The field to retrieve value for
	 * @return mixed The previous value
	 */
	public function getPreviousValue($field)
	{
		if( isset(self::$changed[$this->registry][$field]) )
		{
			return self::$changed[$this->registry][$field];
		}
		return null;
	}

/**
 * This is the function that builds and runs the update query.
 */
	protected function processUpdate()
	{
		if(self::$changed[$this->registry] !== array())
		{
			$schema = $this->getSchema();
			$tables = $this->tableJoinMapping;
			$tables[$this->primaryTable] = $this->primaryKey;
			$set = '';
			foreach($tables as $table =>$key)
			{
				foreach(self::$changed[$this->registry] as $property => $oldval)
				{
					// if valid property
					if(isset($schema[$table][$property]) === true)
					{
						$set .= '`'.$table.'`.`'.$property.'` = "'.$this->site->db->escape((string)self::$schemaValues[$this->registry][$property]).'", ';
					}
				}
			}
			if($set !== '')
			{
				$q = '/* '.$this->__CLASS__.' '.__METHOD__.' */
				UPDATE
				'.$this->buildFrom().'
					SET '.substr($set, 0, -2).'
				WHERE `'.$this->primaryTable.'`.`'.$this->primaryKey.'` = "'.$this->site->db->escape((string)self::$schemaValues[$this->registry][$this->primaryKey]).'"';
				if($this->site->db->query($q))
				{
					// we updated the primary key..
					if(array_key_exists($this->primaryKey, self::$changed[$this->registry]) === true)
					{
						$this->moveRegistry(self::$schemaValues[$this->registry][$this->primaryKey]);
					}

					self::$changed[$this->registry] = array();
					return true;
				}
			}
		}
		return false;
	}

/**
 * Updates database with local values.
 * @return bool true on success, false on failure
 */
	public function update()
	{
		if( $this->validateUpdate() === false )
		{
			return false;
		}
		foreach( $this->onBeforeUpdate as $func )
		{
			$this->$func();
		}
		if( $this->processUpdate() === true )
		{
			foreach( $this->onAfterUpdate as $func )
			{
				$this->$func();
			}
			self::$changed[$this->registry] = array();
			self::$extrafieldChanged[$this->registry] = array();
			return true;
		}
		return false;
	}
	/**
	 * Retrieves an iteratable array of objects SchemaBased off the field, comparison, and value parameters.
	 * Optionally you can pass a WHERE statement string.
	 * PARAM string $field OPTIONAL The field to search by
	 * PARAM string $comparison OPTIONAL The type of comparison
	 * PARAM mixed $value OPTIONAL The value to be searched (an array for list searches)
	 */
	public function selectList()
	{
		$list = new SchemaList($this);
		// attempt search
		call_user_func_array(array($list, 'select'), func_get_args());
		return $list;
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
		if(isset(self::$expireList[$this->registry][$property]))
		{
			foreach(self::$expireList[$this->registry][$property] as $func => $b)
			{
				unset(self::$expireList[$this->registry][$property][$func], self::$extended[$this->registry][$func]);
			}
		}

		if(isset(self::$expireList[$this->registry]['*']))
		{
			foreach(self::$expireList[$this->registry]['*'] as $func => $b)
			{
				unset(self::$expireList[$this->registry]['*'][$func], self::$extended[$this->registry][$func]);
			}
		}
	}
	/**
	 * Loads and returns a query object ready for refined searching
	 * @param mixed $args [optional] Series of args the SchemaBase::buildWhere method accepts, or no args to not initialize a where statement
	 * @return SchemaQuery The primed query object
	 */
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
