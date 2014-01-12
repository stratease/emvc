<?php
class ProcessManager
{
	public $site;
	public $group = '';
	/*
	 *@var Number of seconds to automatically expire an "IDLE" process. This leverages the last update date, to determine if the process is actively being updated.
	 * 	**WARNING try and avoid using this as a "fix" for problems that could stem from processes not finishing properly. You should build scripts that don't require constant maitenance through code!!!
	 */
	public $expire = 10500;
	public $runGarbage = true;
	function __construct($site, $group = null)
	{
		$this->site = $site;
		if($group !== null)
		{
			$this->group = $group;
		}
	}

	function __get($field)
	{
		switch($field)
		{
			case 'numProcesses':
				$q = "SELECT COUNT(*) as `count`
						FROM `process`
						WHERE `group` = '".$this->site->db->escape($this->group)."'
							AND `statusCode` IN('".Process::P_INITIALIZED."', '".Process::P_INITIALIZING."', '".Process::P_IN_PROGRESS."')";
				if($results = $this->site->db->query($q))
				{
					if($row = $results->fetch_assoc())
					{
						return (int)$row['count'];
					}
				}
				return 0;
			default:
				return null;
		}
	}

	/**
	 * Fork off a separate php process, utilizing the Process class.
	 * @param string $script Absolute path and any args as would be typed in the cli
	 * @param mixed $data Any optional data to pass to the process, where it will load and be able to utilize instantly
	 * @return bool|ProcessChild The process child or false if an error occurs.
	 */
	public function fork($script, $data = null)
	{
		if($this->runGarbage
		   && !rand(0,1))
		{
			$this->runGarbage = false;
			$this->garbage();
		}
		// check if args are being passed...
		if($pos = strpos($script, ' '))
		{
			$args = substr($script, $pos);
			$script = substr($script, 0, $pos);
		}
		else
		{
			$args = ''; // no args...
		}
		if(is_file($script)) // validate file location...
		{
			$process = new ProcessDB($this->site);
			$process->set(array(
				'group' => $this->group,
				'data' => serialize($data),
				'statusCode' => Process::P_INITIALIZING));
			$process->insert();
			// if unix, pass via arg
			if($this->site->isUnix())
			{
				$id = " processId=".$process->get('processId');
			}
			// if win, use curl multi to pass args
			else
			{
				$id = "?_processId_=".$process->get('processId');
			}
			// unix, yay!
			if($this->site->isUnix())
			{
				//shell_exec('nohup php '.$script.$args.$id.' > /tmp/p.out 2> /tmp/p.err < /dev/null &');
				shell_exec('nohup php '.$script.$args.$id.' > /dev/null 2>&1 &');
			}
			// assume windows POS pile of poo that doesn't support multi threading through the command line.
			// why god... why... hack away!
			else
			{
				$maxCnt = 5;
				$retryCnt = 0;
				while($retryCnt < $maxCnt && ($fp = fsockopen('localhost', 80, $errno, $errstr, 0.5)) === false)
				{
					$retryCnt++;
				}
				if (!$fp)
				{
					return false;
				}
				else
				{
					$script = str_replace("\\", "/", $script);
					$out = "GET ".substr($script, strpos($script, WEB_ROOT)).$id." HTTP/1.1\r\n";
					$out .= "Host: localhost\r\n";
					$out .= "Connection: Close\r\n\r\n";

					stream_set_blocking($fp, false);
					stream_set_timeout($fp, 1);
					fwrite($fp, $out);
					fclose($fp);
				}
			}
			return new ProcessChild($this->site,  $process->get('processId'));
		}
		else
		{
			$this->site->error->toss('Unable to locate file: '.$script.' - argument should be an absolute file path.', E_USER_ERROR);
			return false;
		}
	}
	/**
	 * Searchs the process database for a set of children matching the search params.
	 * @param mixed The search params sent to the ProcessDB class
	 * @return ProcessChild on success, false if unable to locate
	 */
	public function getChild()
	{
		if($this->runGarbage
		   && !rand(0,1))
		{
			$this->runGarbage = false;
			$this->garbage();
		}
		$p = new ProcessDB($this->site);
		if(call_user_func_array(array($p, 'select'), func_get_args())) // did we find someone?
		{
			return new ProcessChild($this->site,  $p->get('processId'));
		}
		else
		{
			return false;
		}
	}
	/**
	 * Searchs the process database for a set of children matching the search params.
	 * By default returns only "active" processes.
	 * @param mixed The search params sent to the ProcessDB class
	 * @return associative array of ProcessChild objects, indexed by their processId
	 */
	public function getChildren()
	{
		if($this->runGarbage
		   && !rand(0,1))
		{
			$this->runGarbage = false;
			$this->garbage();
		}
		$search = func_get_args();
		if(empty($search)) // default to 'active' statuses
		{
			$search = array(array(array('statusCode', 'IN', array(Process::P_INITIALIZED, Process::P_INITIALIZING, Process::P_IN_PROGRESS)),array('group', '=', $this->group)));
		}
		$p = new ProcessDB($this->site);
		$list = call_user_func_array(array($p, 'selectList'), $search);
		$children = array();
		foreach($list as $chP)
		{
			$children[$chP->get('processId')] = new ProcessChild($this->site,  $chP->get('processId'));
		}
		return $children;
	}

	public function garbage()
	{
		// clear our expired processes
		$q = "UPDATE `process`
					SET statusCode = 4
				WHERE statusCode IN(0,1,3)
					AND `lastUpdate` < '".$this->site->db->escape(gmdate('Y-m-d H:i:s', strtotime('now -'.$this->expire.' second')))."'";
		$this->site->db->query($q);
		// clear old process rows
		$q = "DELETE
				FROM `process`
				WHERE `lastUpdate` < '".$this->site->db->escape(gmdate('Y-m-d H:i:s', strtotime('now -2 day')))."'";
		$this->site->db->query($q);
	}
}
