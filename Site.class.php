<?php
class Site
{
	/**
	 *@var array of defined config files
	 */
	public $configFiles = array();
	/**
	 * @var assoc site configuration from file
	 */
	public $config = array();

	/**
	 * @var object error class instance
	 */
	public $error;

	/**
	 * @var string publicFolder of the called script, not this class. Set in __construct
	 */
	public $publicFolder;

	/**
	 * @var string absolute folder of the application structure
	 */
	public $appFolder;

	/**
	 * @var string working folder of the core library
	 */
	public $coreFolder;

	/**
	 * List of plugins for events
	 */
	public $plugins = array();

	/**
	 * @var array contains various lazy loaded data
	 */
	protected $extended = array();

	function __construct($config)
	{
		// get calling script location -
		// @TODO test in windows
		if (!empty($_SERVER['DOCUMENT_ROOT']))
		{
			$this->publicFolder = realpath($_SERVER['DOCUMENT_ROOT']);
		}
		else
		{
			// assume CLI?
			//this works in all CLI cases, instead of getcwd() which will lie if you call the script from another location.
			$this->publicFolder = dirname(realpath($GLOBALS['argv'][0]));
		}
		if(is_array($config))
		{
			$configs = $config;
		}
		else
		{
			$configs = array($config);
		}
		// first config is defined as primary location where all paths inherit from
		$this->appFolder = dirname(realpath($configs[0])).DIRECTORY_SEPARATOR;

		$this->coreFolder = dirname(__FILE__).DIRECTORY_SEPARATOR;
		// check primary file
		if(is_file($configs[0]) === false)
		{
			trigger_error("Primary Config file '".$configs[0]. "' not found. Must be specified as a paramater to Site::__construct()", E_USER_ERROR);
			exit(1);
		}
		// load initial configs
		foreach($configs as $config)
		{
			$this->configFiles[] = realpath($config);
			$this->addConfig($config);
		}
		spl_autoload_register(array($this, 'autoLoadClasses'));
		// setup error class, hopefully nothing breaks before this!
		$this->error = new Error($this, (bool)$this->config('site', 'errorTakeOver'));
		if ($this->config('site', 'debug'))
		{
			ini_set('display_errors', true);
			error_reporting(E_ALL);
		}
		$this->event = new Event($this);
		$this->startup();
	}
	/**
	 * detects the type of config file and loads the appropriate parser, returning the associative array of day.
	 * @param string $fileName The file path
	 * @return array
	 */
	public function parseConfig($fileName)
	{
		$ext = substr($fileName, strrpos($fileName, '.') + 1);
		switch($ext)
		{
			case 'yml':
				return Symfony\Component\Yaml\Yaml::parse($fileName);
			case 'ini':
			default:
				return parse_ini_file($fileName, true);
		}
	}
	/**
	 * Add various custom configuration files or primary configuration overrides. The files merge with the main configuration, overwriting any previously defined config values
	 * @param string $configFile the file
	 * @param string [optional] $nameSpace The top level namespace to force the file to import under. Useful for dynamic inclusions based on a particular namespace
     * @param bool $override Flag whether to override config value
	 * @return bool True on success, false on error
	 */
	public function addConfig($configFile, $nameSpace = null, $override = true)
	{
		$s = true;
		if(is_dir($configFile))
		{
			$files = scandir($configFile);
			foreach($files as $file)
			{
				if(substr($file, 0, 1) === '.') // hidden file/folder ignore it!
				{
					continue;
				}
				if(!$this->addConfig(realpath($configFile.'/'.$file)))
				{
					$s = false;
				}
			}
		}
		else if(is_file($configFile))
		{
			if($cData = $this->parseConfig($configFile))
			{
				foreach($cData as $i => $v)
				{
					if(is_array($v))
					{
						foreach($v as $j => $vj)
						{
							if($nameSpace !== null)
							{
                                if($override === true
                                   || isset($this->config[$nameSpace][$i][$j]) === false) {
    								$this->config[$nameSpace][$i][$j] = $vj;
                                }
							}
							else if($override === true
                                    || isset($this->config[$i][$j]) === false) {
							    $this->config[$i][$j] = $vj;
							}
						}
					}
					else
					{
						if($nameSpace !== null)
						{
                            if($override === true
                                || isset($this->config[$nameSpace][$i]) === false) {
							    $this->config[$nameSpace][$i] = $v;
                            }
						}
						else if($override === true
                            || isset($this->config[$i]) === false) {
							$this->config[$i] = $v;
						}
					}
				}
				//$this->config = array_merge($this->config, $cData);
				// do we have more configs?
				if(isset($cData['site']['configs']))
				{
					foreach($cData['site']['configs'] as $c)
					{
						if(!$this->addConfig(realpath(dirname($configFile).'/'.$c)))
						{
							$s = false;
						}
					}
				}
			}
			else
			{
				if(is_array($cData) === false)
					trigger_error('Unable to parse configuration file: '.$configFile, E_USER_ERROR);
			}
		}
		return $s;
	}

	/**
	 * auto loads the class files
	 */
	public function autoLoadClasses($className)
	{
		if($this->findClass($className, $this->coreFolder))
		{
			return;
		}
		// check our core subfolders...
		$files = scandir($this->coreFolder, SCANDIR_SORT_NONE);
		foreach($files as $file)
		{
			if(is_dir($this->coreFolder.$file))
			{
				if($this->findClass($className, $this->coreFolder.$file))
					return;
			}
		}
		if($this->findClass($className, $this->config('site', 'autoloaderPaths')))
		{
			return;
		}
		if($this->findClass($className, $this->config('site', 'pluginPath')))
		{
			return;
		}
		if($this->findClass($className, $this->config('site', 'controllerPath')))
		{
			return;
		}
	}
	/**
	 *Find the specific class, in the path(s) specified. If found, return true
	 *@param string $className The class name
	 *@param mixed $paths String or array of paths to search
	 */
	private function findClass($className, $paths)
	{
		if($paths === null)
		{
			return;
		}
		if(is_array($paths) === false)
		{
			$paths = array($paths);
		}
		foreach( $paths as $pth )
		{
			if (substr(trim($pth), 0, 1) != '/')
			{
				// relative path, prepend app folder
				$pth = $this->appFolder.$pth;
			}
			if (is_file($pth.DIRECTORY_SEPARATOR.$className.".class.php"))
			{
				require_once($pth.DIRECTORY_SEPARATOR.$className.".class.php");
				return true;
			}
			// useful for 3rd party stuff
			if (is_file($pth.DIRECTORY_SEPARATOR.$className.".php"))
			{
				require_once($pth.DIRECTORY_SEPARATOR.$className.".php");
				return true;
			}
			// dirs PEAR style autoload files
			$f = $pth.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, explode("_", $className)).".php";
			if(is_file($f))
			{
				require_once($f);
				return true;
			}
		}
		return false;
	}

	function __get($field)
	{
		if( isset($this->extended[$field]) === false )
		{
			switch( $field )
			{
				case 'request':
					$this->extended[$field] = new Request($this);
					break;
				case 'response':
					$this->extended[$field] = new Response($this);
					break;
				case 'session':
					$this->extended[$field] = new Session($this);
					break;
				case 'webCatalog':
					$this->extended[$field] = new WebCatalog($this);
					break;
				case 'db':
					$this->extended[$field] = new Database($this, $this->config('db', 'host'), $this->config('db', 'user'), $this->config('db', 'password'), $this->config('db', 'database'));
					break;
				case 'server':
					$this->extended[$field] = new Server($this);
					break;
				case 'mongoDB':
					if($this->config('mongo', 'database'))
					{
						try {
							$this->extended[$field] = $this->mongoClient->selectDB($this->config('mongo', 'database'));
						} catch(Exception $e)
						{
							$this->error->toss($e->getMessage());
						}
					}
					else
					{
						$this->error->toss('No mongo database defined. [mongo][database]');
					}
					break;
				case 'mongoClient':
					if(!($options = $this->config('mongo', 'options')))
					{
						$options = array();
					}
					try {
						$this->extended[$field] = new MongoClient($this->config('mongo', 'server'), $options);
					} catch(Exception $e)
					{
						$this->error->toss($e->getMessage());
					}
					break;
				default:
					return null;
			}
		}
		return $this->extended[$field];
	}

	/**
	 * Finds and renders the associated page
	 */
	public function render()
	{
		// lets check if we are doing some cli magic...
		if($this->isCLI())
		{
			// do we have args? if no args, fail gracefully to a normal page render...
			if(!empty($_SERVER['argc'])
			   && !empty($_SERVER['argv'])
			   && $_SERVER['argc'] > 1)
			{
				// ok delegate cli madness!
				$cliScript = new CLIScript($this);
				$args = $_SERVER['argv'];
				unset($args[0]);// ditch index.php from args
				if($cliScript->loadScript($args[1])) // attempts to find the specific script
				{
					unset($args[0], $args[1]);
					$nargs = array($cliScript->calledScript);
					$args = array_values($args);
					foreach($args as $v)
					{
						$nargs[] = $v;
					}
					$_SERVER['argv'] = $nargs;
					global $argv;
					$argv = $nargs;
					$_SERVER['argc'] = count($_SERVER['argv']);
					global $argc;
					$argc = $_SERVER['argc'];
					$cliScript->pipeIn(null, $nargs);
					return true; // when we are done...
				}
				else
				{
					$this->error->toss('Unable to load ('.$_SERVER['argv'][1].') script.');
				}
			}
			$this->error->toss('CLI operation detected, but no valid script arguments found. Continuing with page rendering.', E_USER_NOTICE);
		}
		// any pre rendering steps here...
		//// start the rendering...
		$this->event->publish("Site::onRender");
		$this->event->subscribe('Page::onRender', array($this, 'renderPlugins'));
		$page = $this->webCatalog->getPageFromPath(str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']));
		$page->render();
	}

	public function renderPlugins()
	{
		if(is_array($this->config('plugins', 'onRender')))
		{
			$v = $this->config('plugins', 'onRender');
			foreach($v as $plugin)
			{
				if(class_exists($plugin, true))
				{
					$this->plugins[$plugin] = new $plugin($this);
				}
				else
				{
					$this->error->toss('Plugin "'.$plugin.'" was not found.', E_USER_WARNING);
				}
			}
		}
	}

	/**
	 * startup function is called automatically upon construction of object.
	 * Use to fire your code.
	 *
	 * @return null
	 */
	public function startup()
	{
		// timezone set?
		if($this->config('site', 'timezone'))
		{
			date_default_timezone_set($this->config('site', 'timezone'));
		}
		// check and load plugins if they're defined.
		if (is_array($this->config('plugins', 'startup')) )
		{
			foreach($this->config('plugins', 'startup') as $plugin)
			{
				if(class_exists($plugin, true))
				{
					$this->plugins[$plugin] = new $plugin($this);
					$this->plugins[$plugin]->startup();
				}
				else
				{
					$this->error->toss('Plugin "'.$plugin.'" was not found.', E_USER_WARNING);
				}
			}
		}
		$this->event->publish("Site::onStartup");
	}


	/**
	 * returns config string by item, taking into account environment
	 * @param string item
	 * @return string config mixed
	 */
	public function config()
	{
		$args = func_get_args();
		switch(count($args))
		{
			case 2:
				if (isset($this->config[$args[0]][$args[1]]))
				{
					return $this->config[$args[0]][$args[1]];
				}
				break;
			case 1:
				if (isset($this->config[$args[0]]))
				{
					return $this->config[$args[0]];
				}
				break;
		}
		return null;
	}
	/**
	 * add /override a config setting at run time
	 * @param field(s)
	 * @param value
	 */
	public function setConfig()
	{
		$args = func_get_args();
		switch(count($args))
		{
			case 2:
				$this->config[$args[0]] = $args[1];
				break;
			case 3:
				$this->config[$args[0]][$args[1]] = $args[2];
				break;
			case 4:
				$this->config[$args[0]][$args[1]][$args[2]] = $args[3];
				break;
			default:
				$this->error->toss("Unable to set a config with ".count($args)." parameters!", E_USER_ERROR);
				break;
		}
	}

	/**
	 * checks OS
	 * @todo put this somewhere useful. Maybe a server object?
	 * @return bool    true if unix variant
	 */
	function isUnix()
	{
		return ($this->isWindows() === false);
	}

	/**
	 * returns true if running on windows
	 * @todo put this somewhere useful
	 * @return bool    true if windows
	 */
	function isWindows()
	{
		return is_int(stripos(php_uname('s') , 'windows'));
	}

	/** returns true if the calling script is being run from the command line
	 * @return bool true if CLI, false otherwise
	 */
	function isCLI()
	{
		$interface = php_sapi_name();
		if ($interface == 'cli')
		{
			return true;  // request var is only created when a browser calls the script via mod_php or CGI
		}
		return false;
	}
	/**
	 * returns true if on a 32 bit arch
	 * @todo put this somewhere useful.
	 *
	 * @return bool    True if 32bit
	 */
	function is_32bit()
	{
		 // for 32 bit servers, according to PHP docs integers out of range will be converted into floats...
		return is_float(9223372036854775807);
	}

/**
 * Returns the request directly - is this even useful?
 *
 * @return string    Request object->toString()
 */
	public function __toString()
	{
		return (string)$this->request;
	}
}





/**
 * Function attempts to clean up various encoding types to UTF-8 and removes/translates invalid characters as best as possible.
 * The params list is identical to json_decode, with our clean up happening before passing params to json_decode.
 * @param string $json The json string to clean up and decode to the object/array
 * @param bool $assoc
 * @param int $depth
 * @param int $options
 */
function json_decode_clean()
{
	$args = func_get_args();
	$args[0] = mb_convert_encoding(preg_replace('/[\x00-\x1F\x80-\xFF]/', "", $args[0]),'UTF-8');
	return call_user_func_array('json_decode', $args);
}
