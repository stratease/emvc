<?php
/**
* Interface for communicating with the child processes.
*/
class ProcessChild extends Process
{

	function __construct($site, $id)
	{
		$this->site = $site;
		$this->process = new ProcessDB($this->site);
		$this->process->select($id);
		$this->group = $this->process->get('group');
	}

	function __set($field, $value)
	{
		switch($field)
		{
			case 'data':
				$this->_data = $value;
				$this->process->set('data', serialize($value));
				$this->process->update();
				break;
		}
	}

	/**
	 * issue a kill command to the child process
	 */
	public function kill()
	{
		$this->process->set('statusCode', Process::P_KILL_ISSUED);
		$this->process->update();
	}
	/**
	 * @return bool True if killed, complete, or errored out.
	 */
	public function isDone()
	{
		return ($this->process->get('statusCode') === Process::P_COMPLETE
					|| $this->process->get('statusCode') === Process::P_ERROR
					|| $this->process->get('statusCode') === Process::P_KILLED);
	}

	function __get($field)
	{
		if($this->lastRefresh < (microtime(true) - 0.3)) // check max frequency... @TODO put as a config?
		{
			$this->refresh();
		}
		switch($field)
		{
			case 'progress':
				return (int)round(parent::__get('progress')); // convert float to integer for reporting
			default:
				return parent::__get($field);
		}
	}

	function __destruct()
	{
		// override parents
	}
}
