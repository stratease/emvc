<?php
class CLIScript
{
	public $site = null;
	static $_scriptPath = 'cli/_cmds/';
	function __construct($site)
	{
		$this->site = $site;
	}
	/**
	 * @todo Finish this/ remove? Dont' remember train of thought when starting this...
	 */
	protected function getScriptIdent()
	{
		$loadArgs = $_SERVER['argv'];
		array_shift($loadArgs);
		$idents = ''; // valid script paths
		foreach($loadArgs as $a)
		{
			if(substr($a, 0, 1) === '-')
			{
				return $idents;
			}
			else if(realpath($this->getRootPath().$idents.$a))
			{
				$idents .= $a.DIRECTORY_SEPARATOR;
			}
			else
			{
				return $idents;
			}
		}
	}
	protected function getRootPath()
	{
		return $this->site->coreFolder.DIRECTORY_SEPARATOR.self::$_scriptPath;
	}
	public function loadScript()
	{
		$args = func_get_args();
		// args define folder paths to the scripts
		if(count($args) === 0)
		{
			return false;
		}
		else
		{
			if(is_string($args[0]))
			{
				if(strtolower(substr($args[0], -4)) !== '.php')
				{
					$fname = $args[0].'.php';
				}
				else
				{
					$fname = $args[0];
				}
			}
			else
			{
				$fname = null;
				$f = '';
				foreach($args[0] as $v)
				{
					$f = $f. DIRECTORY_SEPARATOR. $v;
					if(file_exists($f))
					{
						$fname = $f;
					}
					else
					{
						break;
					}
				}
			}
			if(file_exists($this->getRootPath().$fname))
			{
				$this->calledScript = realpath($this->getRootPath().$fname);
				return true;
			}
			return false;
		}
	}

	public function pipeIn($script = null, $args = array())
	{
		if($script === null)
		{
			$script = $this->calledScript;
		}
		else
		{
			$this->loadScript($script);
		}
		if($this->calledScript)
		{
			$site = $this->site;
			require_once($script);
			return true;
		}
		else
		{
			return false;
		}
	}
}
