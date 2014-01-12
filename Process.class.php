<?php

class Process
{
	/**
	 *defines a process that has just been inserted to database, but control hasn't been registered by the process yet...
	 */
	const P_INITIALIZING = 0;
	/**
	 *process has taken control
	 */
	const P_INITIALIZED = 1;
	/**
	 *process completed without error
	 */
	const P_COMPLETE = 2;
	/**
	 *process is running
	 */
	const P_IN_PROGRESS = 3;
	/**
	 *some internal error occurred...
	 */
	const P_ERROR = 4;
	/**
	 *someone killed the process
	 */
	const P_KILLED = 5;
	/**
	 *someone did a soft kill, waits in this status until the process gracefully closes
	 */
	const P_KILL_ISSUED = 6;
	protected $process = null;
	protected $site = null;
	protected $lastRefresh = 0;
	public $group = null;

	function __construct($site, $group = null)
	{
		set_time_limit(0);
		$this->site = $site;
		$this->process = new ProcessDB($site);
		// pull in the global args arrays...
		global $argc;
		global $argv;
		if($this->site->isUnix())
		{
			$v = null;
			// find our processId...
			if(isset($argc) && $argc > 1)
			{
				if(substr($argv[$argc - 1], 0, 10) === 'processId=')
				{
					$v = array_pop($argv); // remove our ninja arg
					$argc--;
					if(isset($_SERVER['argv']))
					{
						array_pop($_SERVER['argv']);
						$_SERVER['argc']--;
					}
				}
			}
			elseif(isset($_SERVER['argc']) && $_SERVER['argc'] > 1)
			{
				if(substr($_SERVER['argv'][$_SERVER['argc'] - 1], 0, 10) === 'processId=')
				{
					$v = array_pop($_SERVER['argv']);
					$_SERVER['argc']--;
					if(isset($argv))
					{
						array_pop($argv);
						$argc--;
					}
				}
			}
			// no process id, we must be creating ourself
			if($v === null)
			{

			}
			// find our process....
			elseif($this->process->select(array(array('processId', '=', substr($v, 10)),
														array('statusCode', '=', array(Process::P_INITIALIZING)))) === false)
			{
				$this->site->error->toss('Invalid proccess id! Error creating forked process. Exiting...', E_USER_ERROR);
			}
		}
		else // windows box
		{
			if(isset($_GET['_processId_']))
			{
				$this->process->select(array(array('processId', '=', $_GET['_processId_']),
													array('statusCode', '=', array(Process::P_INITIALIZING))));
				unset($_GET['_processId_']);
			}
		}

		// set init params
		$this->process->set(array(
									'pid' => getmypid(),
									'startDate' => gmdate('Y-m-d H:i:s'),
									'lastUpdate' => gmdate('Y-m-d H:i:s'),
									'statusCode' => self::P_INITIALIZED));
		// if we aren't loaded that means we are starting a new process
		if($this->process->isLoaded === false)
		{
			// group should be defined when creating a new process...
			if($group === null)
			{
				$this->site->error->toss('Must define a process group when creating a new process.', E_USER_ERROR);
			}
			$this->process->set('group', $group);
			$this->process->insert();
		}
		// ... otherwise we are loading a forked process and flagging we have gained control ...
		else
		{
			// group should have already been setup
			$this->process->update();
		}
		$this->group = $this->process->get('group');
	}

	function __set($field, $value)
	{
		switch($field)
		{
			case 'progress':
				if($value >= 100)
				{
					if($this->process->get('statusCode') === self::P_INITIALIZED ||
					   $this->process->get('statusCode') === self::P_IN_PROGRESS)
					{
						$this->process->set('statusCode', self::P_COMPLETE);
					}
					$value = 100;
				}
				else
				{
					if($this->process->get('statusCode') === self::P_INITIALIZED)
					{
						$this->process->set('statusCode', self::P_IN_PROGRESS);
					}
				}
				$this->process->set('progress', $value);
				$this->process->update();
				break;
			case 'data':
				$this->process->set('data', serialize($value));
				$this->process->update();
				break;
			default:
				break;
		}
	}

	function __get($field)
	{
		switch($field)
		{
			case 'requestedKill':
				if($this->lastRefresh < (microtime(true) - 0.3)) // check max frequency...
				{
					$this->refresh();
				}
				return $this->process->get('statusCode') === self::P_KILL_ISSUED;
			case 'isKilled':
				if($this->lastRefresh < (microtime(true) - 0.3)) // check max frequency...
				{
					$this->refresh();
				}
				return $this->process->get('statusCode') === self::P_KILLED;
			case 'data':
				if($this->lastRefresh < (microtime(true) - 0.3)) // check max frequency...
				{
					$this->refresh();
				}
				$data = null;
				if($this->process->get('data') != '')
				{
					$data = unserialize($this->process->get('data'));
				}
				return $data;
			case 'statusString':
				if($this->lastRefresh < (microtime(true) - 0.3)) // check max frequency...
				{
					$this->refresh();
				}
				switch($this->process->get('statusCode'))
				{
					case 0:
						return 'Initializing';
					case 1:
						return 'Initialized';
					case 2:
						return 'Completed';
					case 3:
						return 'In Progress';
					case 4:
						return 'Error';
					case 5:
						return 'Canceled';
					case 6:
						return 'Canceling';
					default:
						return 'unknown';
				}
			default:
				return $this->process->get($field);
		}
	}

	protected function refresh()
	{
		$q = "SELECT *
						FROM `process`
						WHERE `processId` = '".$this->site->db->escape($this->process->get('processId'))."'";
		if($results = $this->site->db->query($q))
		{
			if($results->num_rows)
			{
				$this->lastRefresh = microtime(true);
				$this->process->loadRow($results->fetch_assoc(), true); // force a data override...
			}
		}
	}

	/*
	 * trims/cleans up database
	 */
	public function garbage()
	{
		$this->process->selectList(array(array('finishDate', '<', gmdate('Y-m-d H:i:s', strtotime('yesterday')))))->delete();
	}

	/*
	 *End your process
	 */
	public function shutdown()
	{
		$this->refresh();
		if($this->process->get('statusCode') === self::P_KILL_ISSUED)
		{
			$this->process->set('statusCode', self::P_KILLED);
		}
		else if($this->process->get('statusCode') !== self::P_ERROR && $this->process->get('statusCode') !== self::P_KILLED )
		{
			$this->process->set(array('statusCode' => self::P_COMPLETE));
		}
		$this->process->set(array(	'finishDate' => gmdate('Y-m-d H:i:s')));
		$this->process->update();
		if(!rand(0, 1000)) // every 1000 times do some cleanup...
		{
			$this->garbage();// @TODO - thread here...
		}
	}

	function __destruct()
	{
		// make sure we didn't kill off the other objects yet.... @TODO - do this in a more system safe manner
		if($this->process && $this->process->site->db && $this->process->get('processId'))
		{
			$this->shutdown();
		}
	}
}
