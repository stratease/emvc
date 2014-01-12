<?php
class ConsolePrompt
{
	public $site = null;
	function __construct($site)
	{
		$this->site = $site;
	}
	public function ask($q, $cleanRes = true)
	{
		if (trim(shell_exec("/usr/bin/env bash -c 'echo OK'")) !== 'OK')
		{
			$this->site->error->toss("Can't invoke bash");
			return false;
		}
		else
		{
			echo "\n";
			if(class_exists('Console_Color2'))
				$q = Console_Color2::convert($q);
			//$s = "/usr/bin/env bash -c \"read -p '" . addcslashes($q, '"'). " ' v && echo \\\$v\"";//echo "\n".$s."\n";//exit;
			//$res = shell_exec($s);
			$res = readline($q);
			if($cleanRes)
				return trim($res);
			else
				return $res;
		}
	}
	public function msg($q)
	{
		if(class_exists('Console_Color2'))
			$q = Console_Color2::convert($q);
		echo "\n".$q;
	}
}
