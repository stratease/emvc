<?php
class PageUtil
{
	/*
	 *@param Site $site The site object
	 */
	function __construct($site)
	{
		$this->site = $site;
		$this->_get = $_GET;
		$this->_path = $_SERVER['REQUEST_URI'];
	}
	/*
	 *@param string $q The query string of the format "bob=123&frank=4321" to be inserted in the current query string
	 *@return PageUtil This page object
	 */
	public function addQuery($q)
	{
		if(is_string($q))
		{
			parse_str($q, $res);
			$this->_get = array_merge($this->_get, $res);
		}
		return $this;
	}
	public function setURL($path)
	{
		$parsed = parse_url($path);
		if(isset($parsed['scheme']))
			$s = $parsed['scheme'].'://';
		else
			$s = 'http://';
		if(isset($parsed['host']))
		{
			if(isset($parsed['port']))
			{
				$this->_path = $s.$parsed['host'].':'.$parsed['port'].$parsed['path'];
			}
			else
			{
				$this->_path = $s.$parsed['host'].$parsed['path'];
			}
		}
		else
		{
			$this->_path = $parsed['path'];
		}
		if(!empty($parsed['query']))
		{
			$this->addQuery($parsed['query']);
		}
	}
	/*
	 * Retrieves the current query string
	 *@return string The query string
	 */
	public function getQuery()
	{
		return http_build_query($this->_get);
	}
	/**
	 * rewrites the url from a configuration
	 */
	public function rewriteWithConfig($url, $rewrites)
	{
		if(isset($rewrites[0]))
		{
			foreach($rewrites as $rewrite)
			{
				$url = $this->rewriteWithConfig($url, $rewrite);
			}
		}
		else
		{
			// do a match..
			$mtch = str_replace(["}","{"], "", $rewrites['target']);
			if(preg_match("/".preg_quote($mtch, "/")."/", $url))
			{
				// found one... now do replacement parsing
				if(preg_match_all("/{(.*?)}/", $rewrites['target'], $m))
				{
					$targets = $m[1];
					if(preg_match_all("/{(.*?)}/", $rewrites['replace'], $m))
					{
						$replaces = $m[1];
						foreach($targets as $i => $targ)
						{
							if(isset($replaces[$i]))
							{
								$url = preg_replace("/".preg_quote($targ, "/")."/", $replaces[$i], $url);
							}
							else
							{
								$this->site->error->toss("Invalid rewrite rule: ".json_encode($rewrites), E_USER_WARNING);
							}
						}
					}
					else
					{
						$this->site->error->toss("Invalid rewrite rule: ".json_encode($rewrites), E_USER_WARNING);
					}
				}
			}
		}
		return $url;
	}
	/**
	 * The full relative page path with query string attached.
	 */
	public function __toString()
	{
		if($q = $this->getQuery())
			$str = $this->_path.'?'.$q;
		else
			$str = $this->_path;
		if($rewrite = $this->site->config('rewrite'))
		{
			$str = $this->rewriteWithConfig($str, $rewrite);
		}
		return $str;
	}
	 /**
	 * rewrites the current URL based on values given in assoc array
	 * @param assoc associative array giving one or more of the following:
	 *  - scheme
	 *  - user
	 *  - pass
	 *  - host
	 *  - subdomain
	 *  - path
	 *  - query
	 *  - port
	 *  if called from the command line, script will return the current path.
	 * @return string    URL
	 */
	public function rewriteURL($replace)
	{
			$aURL = array();

			if ($this->site->isCLI())
			{
					return dirname(__FILE__);
			}

			// Try to get the request URL
			if (!empty($_SERVER['REQUEST_URI']))
			{
					$aURL = parse_url($_SERVER['REQUEST_URI']);
			}

			// Fill in the empty values
			if (empty($aURL['scheme']))
			{
					if (!empty($_SERVER['HTTP_SCHEME']))
					{
							$aURL['scheme'] = $_SERVER['HTTP_SCHEME'];
					}
					else
					{
							if ($this->site->config('host', 'https'))
							{
									$aURL['scheme'] = 'https';
							}
							else
							{
									$aURL['scheme'] = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') ? 'https' : 'http';
							}
					}
			}
			if (empty($aURL['host']))
			{
					if (!empty($_SERVER['HTTP_HOST']))
					{
							if (strpos($_SERVER['HTTP_HOST'], ':') > 0)
							{
									list($aURL['host'], $aURL['port']) = explode(':', $_SERVER['HTTP_HOST']);
							} else {
									$aURL['host'] = $_SERVER['HTTP_HOST'];
							}
					}
					else if (!empty($_SERVER['SERVER_NAME']))
					{
							$aURL['host'] = $_SERVER['SERVER_NAME'];
					} else {
							user_error('HostInfo::rewriteURL failed to generate hostname correctly.', E_USER_WARNING);
							return '';
					}
			}

			$hostBreakdown = explode('.', $aURL['host']);
			if (count($hostBreakdown) > 2)
			{
					$aURL['subdomain'] = array_shift($hostBreakdown).".";

			}
			else
			{
					$aURL['subdomain'] = '';
			}
			$aURL['domain'] = implode('.', $hostBreakdown);
			if (empty($aURL['port']) && !empty($_SERVER['SERVER_PORT']) )
			{
					$aURL['port'] = $_SERVER['SERVER_PORT'];
			}

					   if (!empty($aURL['query']))
			{
					$aURL['query'] = '?'.$aURL['query'];
			}

			if (isset($replace['host']))  // if specified as a host, break it up to match aURL
			{
					$hostBreakdown = explode('.', $replace['host']);
					if (count($hostBreakdown) > 2)
					{
							$replace['subdomain'] = array_shift($hostBreakdown).".";
					}
					else
					{
							$replace['subdomain'] = '';
					}
					$replace['domain'] = implode('.', $hostBreakdown);
			}

			$aURL = array_merge($aURL, $replace);
			// Build the URL: Start with scheme, user and pass
			$sURL = $aURL['scheme'].'://';
			if (!empty($aURL['user']))
			{
					$sURL .= $aURL['user'];
					if (!empty($aURL['pass'])) {
							$sURL .= ':'.$aURL['pass'];
					}
					$sURL .= '@';
			}

			// Add the host
			$sURL .= $aURL['subdomain'].$aURL['domain'];

			// Add the port if needed
			if (!empty($aURL['port']) &&
				(
					($aURL['scheme'] == 'http' && $aURL['port'] != 80)
					|| ($aURL['scheme'] == 'https' && ($aURL['port'] != 443 && $aURL['port'] != 80))
					)
				)
			{
					$sURL .= ':'.$aURL['port'];
			}

			// Add the path and the query string
			//$sURL.= $aURL['path'].@$aURL['query'];
			$sURL .= $aURL['path'].(isset($aURL['query']) ? $aURL['query'] : '');
			// Clean up
			unset($aURL);
			return $sURL;
	}

		/**
         * Join one or more path components intelligently. Modeled after Python's os.path.join()
         * If any component is an absolute path, all previous paths are discarded and joining continues.
         * This does not verify paths.
         * @parmeters string or array of strings.
         * @todo make work with windoze (drive names)
         * @return string concatenated path.
         */
        public function joinPath()
        {
                $path = '';
                $args = func_get_args();
                foreach ($args as $chunk)
                {
                        if (is_array($chunk))
                        {
                                $chunk = call_user_func_array(array($this, 'joinPath'), $chunk);  // call recursively for array chunks
                        }
                        else
                        {
                                $chunk = trim($chunk, '\\/');
                                // normalize seperators for current OS
                                $chunk = str_replace('..', '', $chunk); // just lose .. instead of resolving it? This is easier.
                                $chunk = str_replace('/', DIRECTORY_SEPARATOR, $chunk);
                                $chunk = str_replace('\\', DIRECTORY_SEPARATOR, $chunk);

                        }

                        $path .= DIRECTORY_SEPARATOR.$chunk;

                }

                $path = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path); // lose dups
                return $path.'/';
        }
}
