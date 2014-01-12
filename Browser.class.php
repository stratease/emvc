<?php
class Browser
{
	public $browser = 'Unknown';  // browser brand name
	public $platform = 'Unknown';
	public $version= 'Unknown';
	public $mobile = false;

	function __construct($site)
	{

		if (!isset($_SERVER['HTTP_USER_AGENT']))
		{
			// maybe we're being used in a CLI environment?
			return;
		}

		$u_agent = $_SERVER['HTTP_USER_AGENT'];
		//First get the platform?
		if (preg_match('/linux/i', $u_agent)) {
		    $this->platform = 'linux';
		}
		elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
		    $this->platform = 'mac';
		}
		elseif (preg_match('/windows|win32/i', $u_agent)) {
		    $this->platform = 'windows';
		}

		// Next get the name of the useragent yes seperately and for good reason
		if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
		{
		    $this->name = 'Internet Explorer';
		    $ub = "MSIE";
		}
		elseif(preg_match('/Firefox/i',$u_agent))
		{
		    $this->name = 'Mozilla Firefox';
		    $ub = "Firefox";
		}
		elseif(preg_match('/Chrome/i',$u_agent))
		{
		    $this->name = 'Google Chrome';
		    $ub = "Chrome";
		}
		elseif(preg_match('/Safari/i',$u_agent))
		{
		    $this->name = 'Apple Safari';
		    $ub = "Safari";
		}
		elseif(preg_match('/Opera/i',$u_agent))
		{
		    $this->name = 'Opera';
		    $ub = "Opera";
		}
		elseif(preg_match('/Netscape/i',$u_agent))
		{
		    $this->name = 'Netscape';
		    $ub = "Netscape";
		}
		elseif(preg_match('/Android/i',$u_agent))
		{
		    $this->name = 'Android';
		    $ub = "Android";
		    $this->mobile = true;
		}
		elseif(preg_match('/webOS/i',$u_agent))
		{
		    $this->name = 'HP WebOS';
		    $ub = "WebOS";
		    $this->mobile = true;
		}
		elseif(preg_match('/iPhone/i',$u_agent))
		{
		    $this->name = 'Apple iPhone';
		    $ub = "iPhone";
		    $this->mobile = true;
		}
		 elseif(preg_match('/iPod/i',$u_agent))
		{
		    $this->name = 'Apple iPod';
		    $ub = "iPod";
		    $this->mobile = true;
		}
		 elseif(preg_match('/iPad/i',$u_agent))
		{
		    $this->name = 'Apple iPad';
		    $ub = "iPad";
		}
		elseif(preg_match('/Netscape/i',$u_agent))
		{
		    $this->name = 'Netscape';
		    $ub = "Netscape";
		    $this->mobile = true;
		}
		else
		{
			$ub = '';
		}

		// finally get the correct version number
		$known = array('Version', $ub, 'other');
		$pattern = '#(?<browser>' . join('|', $known) .
		')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
		if (!preg_match_all($pattern, $u_agent, $matches)) {
		    // we have no matching number just continue
		}

		// see how many we have
		$i = count($matches['browser']);
		if ($i != 1) {
		    //we will have two since we are not using 'other' argument yet
		    //see if version is before or after the name
		    if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
			   $this->version= $matches['version'][0];
		    }
		    else {
			   $this->version= $matches['version'][1];
		    }
		}
		else {
		    $this->version= $matches['version'][0];
		}

		// check if we have a number
		if ($this->version==null || $this->version=="") {$this->version="?";}

	}
}
