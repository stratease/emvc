<?php
/**
 * Extension for MySQLi - has some extra error handling and compat with site object.
 */
class Database extends mysqli
{
	private $site = null;
	private $host = null;
	private $user = null;
	private $password = null;
	private $database = null;

	/**
	 *assoc array of all open and mapped connections
	 */
	private $connections = array();
	/**
	 * constructor
	 */
	function __construct($site, $host, $user, $password, $database)
	{
		$this->site = $site;
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->database = $database;

		// fire up the mysqli constructor manually
		parent::__construct($host, $user, $password, $database);
		if ($this->connect_errno) {
			$this->site->error->toss('Connect Error:' . $this->connect_errno.' '.$this->connect_error, E_USER_ERROR);
		}
		$this->query("SET NAMES utf8;");
	}
	/**
	 * Adds a new connection for a specific identifier
	 *@param string $ident The connection identifier
	 *@param string $host The connection host
	 *@param string $user The connection user
	 *@param string $password The connection password
	 *@param string $database The connection database
	 */
	public function addConnection($ident, $host, $user, $password, $database)
	{
		$this->connections[$ident] = new Database($this->site, $host, $user, $password, $database);
		return $this->connections[$ident];
	}
	/**
	 *will look for an existing connection, if none is found will attempt to retrieve from the configuration and load
	 *@param string $ident The connection identifier
	 *@return Database Instance of the database object, with the identified connection credentials.
	 */
	public function connection($ident)
	{
		if(isset($this->connections[$ident]))
		{
			return $this->connections[$ident];
		}
		else
		{
			if($this->site->config($ident,'host'))
			{
				return $this->addConnection($ident, $this->site->config($ident,'host'), $this->site->config($ident,'user'), $this->site->config($ident,'password'), $this->site->config($ident,'database'));
			}
		}
	}
	/**
	 * assuming the connection has failed, will try and reconnect by manually calling the parent.
	 */
	public function reconnect()
	{
		$this->__construct($this->site, $this->host, $this->user, $this->password, $this->database);
	}
	/** extension of built in query class that handles errors using the error class
	 * as well as reconnection issues and some specific error situations
	 */
	function query($query)
	{
		$result = parent::query($query);
		switch ($this->errno)
		{
			// no errors
			case 0:
				return $result;
				// timeouts
			case 1159:
			case 1161:
			case 1205:
			case 1213:
				if (func_num_args() == 1) {
					// wait, and retry once more
					sleep(1);
					return $this->query($query, true);
				}
				// too many connections
			case 1040:
				if (func_num_args() == 1) {
					// wait, and retry once more
					sleep(15);
					$this->reconnect();
					return $this->query($query, true);
				}
				//Lost Connection w/ mysql
			case 2006:
				if (func_num_args() == 1) {
					$this->reconnect();
					return $this->query($query, true);
				}
				// kick out error
			default:
				return $this->site->error->toss($query."\n".$this->errno."\n".$this->error, E_USER_ERROR);
		}
	}
	/** Casts and escapes value passed (works with arrays too)
	 * @param mixed $val - The array to cleanse
	 * @param string $cast - The datatype to cast
	 */
	public function escape($val, $cast = 'string')
	{
		if (is_array($val)) {
			foreach ($val as $key => $value) {
				$val[$key] = $this->escape($value, $cast);
			}
			return $val;
		} else {
			switch ($cast) {
			case 'int':
			case 'integer':
				return $this->real_escape_string((int)$val);
			case 'double':
				return $this->real_escape_string((double)$val);
			case 'float':
				return $this->real_escape_string((float)$val);
			case 'string':
			default:
				return $this->real_escape_string((string)$val);
			}
		}
	}

	function wait($count = 50, $max_wait = 250)
	{
		$wait = 0;
		while ($wait <= $max_wait) {
			$results = $this->query("SHOW PROCESSLIST");
			if ($results->num_rows($results) < $count) {
				return true;
			} else {
				sleep(5);
			}
			$wait++;
		}
	}

	/**
	* Returns a MySQL UUID
	*
	* @return string    uuid_short
	*/
	public function getUUID()
	{
		$q = "SELECT uuid_short() as uuid";
		$row = $this->query($q)->fetch_assoc();
		return $row['uuid'];
	}
}
