<?php
/**
 * Class meant to simulate threading within the php language. 
 *
 * Requires the Process class and thread.php file, that actually runs the process.
 *
 *@todo Test on a linux machine
 */
class Thread
{
	/**
	 *@var array Contains the active processes
	 */
	private $process = array();
	/**
	 *@var Site The site object
	 */
	public $site;
	/**
	 *@var int Property that tracks intervals between pulling process info, to avoid slamming the database on tight loops
	 */
	private $refresh = 0;

	/**
	 *@var array Contains the data retrieved from a separate process
	 */
	private $_data = array();

	/**
	 *@param Site $site instance of the site object
	 */
	function __construct($site)
	{
		$this->site = $site;
	}

	/**
	 * Main method used to kick off a separate "thread".
	 *
	 *@param string $fileName The name of the file that contains the function/class definition required to run the separate procedure
	 *@param mixed $process The process to be run. Either a string of a function name or an array with the instance and method name to be run
	 *@param array $args The list of args to be passed to the process
	 *@param int The max number of concurrent processes for this specific thread
	 *
	 *@return mixed ID of the thread on successful kickoff, false on failure
	 */
	public function fork($fileName, $process, $args, $numProcesses = 1)
	{
		if(file_exists($fileName))
		{
			if(is_array($process) && count($process) === 2 && is_object($process[0]) && is_string($process[1]))
			{
				$group = get_class($process[0]).'|'.$process[1];
			}
			elseif(is_string($process))
			{
				$group = $process;
			}
			else
			{
				return false;
			}

			// push args into an array or default to empty one
			if($args !== null)
			{
				if(!is_array($args))
				{
					$args = array($args);
				}
			}
			else
			{
				$args = array();
			}

			$pm = new ProcessManager($this->site);
			$pm->group = $group.'|'.$fileName;
			if($pm->numProcesses < $numProcesses)
			{
				return $pm->fork(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'engines'.DIRECTORY_SEPARATOR.'thread.php', array($process, $args, $fileName));
			}
		}

		return false;
	}

	/**
	 *Check if a threaded process is finished
	 *
	 *@param int $id The unique ID for the thread
	 *@return bool Returns true if the thread is complete, false otherwise
	 */
	public function isComplete($id)
	{
		// if not already set
		// or refresh timer is old and not already completed
		if(isset($this->process[$id]) === false &&
		   $this->refresh < (microtime(true) - 1))
		{
			$q = "SELECT *
					FROM `process`
					WHERE processId = '".$this->site->db->escape($id)."'
						AND statusCode IN('".Process::P_COMPLETE."')";

			if($results = $this->site->db->query($q))
			{
				$this->refresh = microtime(true);
				if($row = $results->fetch_assoc())
				{
					$this->process[$id] = new ProcessDB($this->site);
					$this->process[$id]->loadRow($row, true);
				}
			}
		}
		return isset($this->process[$id]);
	}

	/**
	 *Pulls the results from a separate thread
	 *
	 *@param int $id The unique ID for the thread
	 *@return mixed The value of the data retrieved, null if none found
	 */
	public function getResult($id)
	{
		if($this->isComplete($id) && array_key_exists($id, $this->_data) === false)
		{
			if($this->process[$id]->get('data') != '')
			{
				$this->_data[$id] = unserialize($this->process[$id]->get('data'));
			}
			else
			{
				$this->_data[$id] = null;
			}
			return $this->_data[$id];
		}
		elseif(array_key_exists($id, $this->_data))
		{
			return $this->_data[$id];
		}
		else
		{
			return null;
		}
	}
}
