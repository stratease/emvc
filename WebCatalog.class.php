<?php
class WebCatalog
{
    protected $site = null;
	public $currentDirectory = null;
    function __construct($site)
    {
        $this->site = $site;
		// find the current web dir
		if( isset($_SERVER['REQUEST_URI']) )
		{
			$this->currentDirectory = dirname($_SERVER['REQUEST_URI']);
		}
	}

	/** generic redirection function, will redirect to another path on the site using http redirects.
	 * MUST BE USED BEFORE ANY OUTPUT
	 */
	public function redirect($path)
	{
		$util = new PageUtil($this->site);
		$util->setURL($path);
		header('Location: '.(string)$util, true, 302);
	}

	public function redirectBack($query = null)
	{
		$util = new PageUtil($this->site);
		if(isset($_SERVER['HTTP_REFERER']))
		{
			$util->setURL($_SERVER['HTTP_REFERER']);
			if($query !== null)
				$util->addQuery($query);
			header("Location: ".(string)$util);
			return true;
		}
		return false;
	}
	public function internalRedirect($path)
	{
		$page = $this->getPageFromPath($path);
		$page->render();
	}

	public function findControllerObj($searchPath, $dirs, &$method)
	{ //echo '<pre>';
		$searchPath = preg_replace("/[^a-zA-Z0-9\/]/", "_", $searchPath);
		//echo $searchPath;exit;
		// resolve path to a searchable location..
		if($searchPath !== '/'
		   && substr($searchPath, -1) === '/')
		{
			$ptmp = substr($searchPath, 0, -1);
		}
		else
		{
			$ptmp = $searchPath;
		}
		// loop on our controller directories, lookin for controllers that match
		foreach($dirs as $controllerDir)
		{
			if(substr($controllerDir,-1) !== '/')
			{
				$controllerDir .= '/';
			}
			// reset for each directory...
			$path = $ptmp;
			if($path === '')
			{
				$path = '/';
			}
			$chks = array();
			// exact path to controller
			$fname = $this->site->appFolder.'/'.$controllerDir.$path.'.class.php'; // object?
//			echo "\n".$path;
//echo "\n".$fname;
			if($file = realpath($fname))
			{
				$chks[] = array('f' => $file,
										'c' => $this->cleanControllerString($path).'Controller', // clean php syntax, and append Controller to name
										'm' => 'indexAction');
			}
			$fname = $this->site->appFolder.'/'.$controllerDir.$path.'/index.class.php'; // folder index?
//echo "\n".$fname;
			if($file = realpath($fname))
			{
				if($str = $this->cleanControllerString($path))
				{
					$c = $str.'_indexController';
				}
				else
				{
					$c = 'indexController';
				}
				$chks[] = array('f' => $file,
										'c' => $c,
										'm' => 'indexAction');
			}
			// maybe not exact path, so a method is at the end of path?
			$mthd = $this->chopPath($path).'Action';
			$fname = $this->site->appFolder.'/'.$controllerDir.$path.'.class.php'; // object?
//echo "\n".$fname;
			if($file = realpath($fname))
			{
				$chks[] = array('f' => $file,
										'c' => $this->cleanControllerString($path).'Controller', // clean php syntax, and append Controller to name
										'm' => $mthd);
			}
			$fname = $this->site->appFolder.'/'.$controllerDir.$path.'/index.class.php'; // folder index?
//echo "\n".$fname;
			if($file = realpath($fname))
			{
				if($str = $this->cleanControllerString($path))
				{
					$c = $str.'_indexController';
				}
				else
				{
					$c = 'indexController';
				}
				$chks[] = array('f' => $file,
										'c' => $c, // clean php syntax, and append Controller to name
										'm' => $mthd);
			}
			foreach($chks as $chk)
			{
				//echo "\n".__LINE__.' '.__METHOD__.' chk file '.$chk['f'];
				//echo "\n".__LINE__.' '.__METHOD__.' chk method '.$chk['m'];
				//echo "\n".__LINE__.' '.__METHOD__.' chk class '.$chk['c'];
				require_once($chk['f']);
				if(class_exists($chk['c']))
				{
					$chk['m'] = $this->cleanControllerString($chk['m']);
					if(is_callable(array($chk['c'], $chk['m'])))
					{
						$method = $chk['m'];
						return new $chk['c']($this->site);
					}
				}
			}
		}//exit;
		return null;
	}

	public function cleanControllerString($string)
	{
		// invalid chars
		if(substr($string, 0, 1) === '/')
		{
			$string = substr($string, 1);
		}
		// invalid chars replace with underscores...
		$string = preg_replace('/[^0-9A-Za-z_]/', '_', $string);
		if(is_numeric(substr($string, 0, 1))) // can't start with a number
		{
			$string = '_'.$string;
		}
		return $string;
	}

	public function chopPath(&$path)
	{
		if(substr($path, -1) === '/')
		{
			$path = substr($path, 0, -1); // chop of end slash
		}
		if(($pos = strrpos($path, "/")) !== false)
		{
			$piece = substr($path, $pos + 1);
			$path = substr($path, 0, $pos);
			return $piece;
		}
		else
		{
			$path = '/';
			return $path;
		}
	}

	public function getPageFromPath($path)
	{
		$path = trim($path);
		$this->requestedPath = $path;
		$this->requestedPathList = array_values(array_filter(explode("/", $path), 'strlen'));
		$page = new Page($this->site, $path);
		if(is_array($this->site->config('site','controllerPath')))
		{
			$controllerPaths = $this->site->config('site','controllerPath');
		}
		else
		{
			$controllerPaths = array($this->site->config('site','controllerPath'));
		}

		// search for a valid controller...
		$pathStack = array();
		$pathPiece = null;
		$ctrlMethod = null;
		do
		{
			// this finds a match for a path, could still be the wrong object based on other considerations
			if($cntrObj = $this->findControllerObj($path, $controllerPaths, $ctrlMethod))
			{
				// exact path or ... if we didn't find an exact match on first check then we have path pieces here, so is it dynamic? if not this isn't the correct controller
				if(count($pathStack) === 0
				   || (count($pathStack) !== 0 && $cntrObj->dynamicPage === true))
				{
				   $page->loadController($cntrObj,$ctrlMethod,array(array_reverse($pathStack))); // dynamic controller
				   $this->actualPath = $path;
				   break;
				}
			}
			$pathPiece = $this->chopPath($path);
			$pathStack[] = $pathPiece;
		}
		while($path !== $pathPiece); // if same, we are at the base path, no more parsing to be done
		//exit;
		return $page;
	}
}
