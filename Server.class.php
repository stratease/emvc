<?php
/**
 *
 * @author Edwin Daniels <stratease@gmail.com>
 */
class Server
{
	public $site = null;
	function __construct($site)
	{
		$this->site = $site;
	}

	/**
	 * lazy parse various values...
	 */
	function __get($field)
	{
		switch($field)
		{
			case 'subDomain':
				if(isset($_SERVER['HTTP_HOST']))
				{
					$host = $_SERVER['HTTP_HOST'];
				}
				else if(isset($_SERVER['SERVER_NAME']))
				{
					$host = $_SERVER['SERVER_NAME'];
				}
				else {
					$host = '';
				}
				// now split up and grab subdomain if there is one...
				$hs = explode(".", $host);
				if(count($hs) > 2)
				{
					return $hs[0];
				}
				return null;
		}
	}
}
