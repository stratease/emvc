<?php
/** Request class deals with the http request made by the browser
 * The idea is to translate between HTTP requests and PHP OOP structures
 * @todo Deal with file uploads better
 *
 */

class Request
{
	/**
	 * @var object to publish
	 */
	public $path = array('index.php');
	/**
	 * @var array of GET/POST parameters passed in current request.
	 */
	public $parameters = array();
	/**
	 * @var string this is the current path the user 'thinks' they're on.
	 */
	public $virtualPath;
	/**
	 * @var string this is the base path the call was recieved to. Should really use $site instead of this, although they'll probably always match up.
	 */
	public $basePath;

	private $site;


	private $uploadedFile;

	/**
	 * @var int Unix timestamp of the request
	 */
	private $requestTime;

	/**
	 * @var boolean true if the request is a json request.
	 */
	public $json = false;

	/**
	 * @var array Array of http codes and descriptions. Not used currently.
	 */
	private $httpCodes = array(
		// [Informational 1xx]
		100=>'100 Continue',
		101=>'101 Switching Protocols',
		// [Successful 2xx]
		200=>'200 OK',
		201=>'201 Created',
		202=>'202 Accepted',
		203=>'203 Non-Authoritative Information',
		204=>'204 No Content',
		205=>'205 Reset Content',
		206=>'206 Partial Content',
		// [Redirection 3xx]
		300=>'300 Multiple Choices',
		301=>'301 Moved Permanently',
		302=>'302 Found',
		303=>'303 See Other',
		304=>'304 Not Modified',
		305=>'305 Use Proxy',
		306=>'306 (Unused)',
		307=>'307 Temporary Redirect',
		// [Client Error 4xx]
		400=>'400 Bad Request',
		401=>'401 Unauthorized',
		402=>'402 Payment Required',
		403=>'403 Forbidden',
		404=>'404 Not Found',
		405=>'405 Method Not Allowed',
		406=>'406 Not Acceptable',
		407=>'407 Proxy Authentication Required',
		408=>'408 Request Timeout',
		409=>'409 Conflict',
		410=>'410 Gone',
		411=>'411 Length Required',
		412=>'412 Precondition Failed',
		413=>'413 Request Entity Too Large',
		414=>'414 Request-URI Too Long',
		415=>'415 Unsupported Media Type',
		416=>'416 Requested Range Not Satisfiable',
		417=>'417 Expectation Failed',
		// [Server Error 5xx]
		500=>'500 Internal Server Error',
		501=>'501 Not Implemented',
		502=>'502 Bad Gateway',
		503=>'503 Service Unavailable',
		504=>'504 Gateway Timeout',
		505=>'505 HTTP Version Not Supported'
	);

	public $post = array();
	public $get = array();

	/**
	 * @var string full url + path (no query string)
	 * @todo give a sane value for CLI usage? or leave blank.
	 */
	public $url = '';

	/**
	 * @var string hostname
	 */
	public $host = '';
    /**
     * @var null The uploaded files object
     */
    private $_uploadedFiles = null;
	/**
	 *@var bool ** Warning ...
	 *		Suppressing the ability to pull data from the raw php stdin stream will result in unparsed/fetched variables in complex data transfers.
	 *		In order to resolve this, be sure to write the raw stream to a temp stream and pass the stream to the "_rawStream" option.
	 */
	public $_suppressRawStream = false;
	/**
	 *@var resource|null The raw php stdin stream. Only get's set when a complex data transfer is detected, such as json content types or PUT http methods.
	 */
	private $_rawStream = null;
	/**
	 *@var string|null The raw php stdin temp file path. This is only set if a complex data transfer was detected and written to.
	 */
	private $_rawTempFileName = null;
	/**
	 * Request object handles parsing of XML, POST (file uploads), GET, PUT data.
	 * @todo support soap?
	 * @param Site $site The site controlling object
	 * @param array [optional] List of property options to set
	 */
	function __construct($site, $options = array())
	{
		$this->site = $site;
		$this->__CLASS__ = get_class($this);
		$this->requestTime = time();

		foreach($options as $f => $v)
		{
			$this->$f = $v;
		}
		if (isset($_GET['ssid']))
		{
			// deal with ssid issues for some cases.
			session_id($_GET['ssid']);
		}
		// should we grab raw stream?
		if($this->_suppressRawStream === false)
		{
			if(($this->getMethod() === 'PUT')
				|| ($this->isJSON() === true)
				|| ($this->isXML() === true))
			{
				// grab stream and write to file
				// PUT data comes in on the stdin stream
				$putdata = fopen("php://input", "r");
				$this->_rawTempFileName = tempnam(($this->site->config('site', 'tempDir')) ? $this->site->config('site', 'tempDir') : sys_get_temp_dir(), // do we have a temp dir defined?
																			'php_stdin_');
				$this->_rawStream = fopen($this->_rawTempFileName, 'w+');
				/* Read the data 1 KB at a time
				and write to the temp file */
				while ($data = fread($putdata, 1024))
				{
					fwrite($this->_rawStream, $data);
				}
				// done with stdin
				fclose($putdata);
			}
		}
		// get called URL
		if (isset($_SERVER['SERVER_NAME']))
		{
			// assume we're in a browser call and have stock CGI vars (should work in all cases)
			$this->url = $_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
			$this->host = $_SERVER['SERVER_NAME'];
		}
		$this->sanitizeRequest();
	}

	/**
	 * @return bool If this request should be handled as a xml data format. Will attempt to create an XML parsed object if request data is retrieved.
	 */
	public function isXML()
	{
		return ((isset($_SERVER['CONTENT_TYPE'])
						&& $_SERVER['CONTENT_TYPE'] === 'application/xml')); // if sending json data, assume that's the requested format ...
	}
	/**
	 * @return bool If this request should be handled as a json data format. Can imply to return data in the same format based on this
	 */
	public function isJSON()
	{
		return ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) == true
						&& $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') // ajax (assuming json here... )
				|| (isset($_SERVER['CONTENT_TYPE'])
						&& $_SERVER['CONTENT_TYPE'] === 'application/json')); // if sending json data, assume that's the requested format ...
	}

	/**
	 * returns a cast variable with an optional default.
	 * @param string type - type, based on php's settype() function plus a couple of special types 'esc' for db-escaped strings, 'json' for json decoded objects.
	 * @param mixed default - default value, i.e. if it doesn't exist, return this.
	 * @todo CLEAN THIS UP! my brain hurts reading this...
	 */
	function __call($name, $args)
	{
		// default settings are 'string' with a default of ''
		if (array_key_exists(1,$args) === false)
		{
			$args[1] = '';
		}
		if (!isset($args[0]))
		{
			$args[0] = 'string';
		}
		// data might have come in the raw stream if sent via one of these methods
		if(($this->getMethod() === 'PUT' || $this->isJSON() === true || $this->isXML() === true))
		{
			// got it, hopefully
			if($this->isJSON())
			{
				if($json = json_decode($this->_rawString, true))
				{
					if(is_array($json)
					   && array_key_exists($name, $json))
					{
						$val = $json[$name];
					}
				}
			}
			else if($this->isXML())
			{
				$xml = new SimpleXMLElement($this->_rawString);
				if(isset($xml->$name))
				{
					$val = $xml->$name;
				}
			}
			else
			{
				parse_str($this->_rawString, $vals);
				if(isset($vals[$name]))
				{
					$val = $vals[$name];
				}
			}
		}
		// have we got it yet?
		if(isset($val) == false)
		{
			if (isset($_GET[$name]))
			{
				// make a copy so later calls will cast the original
				$val = $_GET[$name];
			}
			elseif (isset($_POST[$name]))
			{
				$val = $_POST[$name];
			}
		}
		// did we find the val above?
		if(isset($val))
		{
			if ($args[0] == 'esc')
			{
				settype($val, 'string');
				$val = mysql_real_escape_string($val);
			}
			elseif ($args[0] == 'json')
			{
				$val = json_decode($val, true);
				if ($val === null)
				{
					$val = $args[1]; // cannot decode, so use default
				}
			}
			elseif ($args[0] == 'boolean')
			{
				if (strtolower($val) == 'false' || $val == '0' || strtolower($val) == 'no')
				{
					return false;
				}
				return true;
			}
			else
			{
				switch($args[0])
				{
					case 'array':
						if($val === '')
						{
							return array();
						}
					default:
						settype($val, $args[0]);
						break;
				}
			}
			return $val;
		}
		else
		{
			// return default.
			settype($args[1], $args[0]);
			return $args[1];
		}
	}

	/**
	 * returns the requested property cast as a string, with '' as default.
	 * This assumes a lot, and can clash with internal properties, so it's not recommended, but useful in templates.
	 *
	 * @param string $name property to return
	 *
	 * @return string    value or '' if not exists
	 */
	function __get($name)
	{
		switch($name)
		{
            case 'files':
                // uploaded files...
                if(isset($this->_uploadedFiles) === false)
                {
                    $this->_uploadedFiles = new UploadedFiles($this->site);
                }
                return $this->_uploadedFiles;
			case '_rawString':
				// grab raw stream file and output as a string
				return stream_get_contents($this->_rawStream,-1,0); // grab entire stream's contents
			default:
				return $this->$name('string', '');
		}
	}

	/**
	 * If called directly, return original request location.
	 * @return string - This parameters
	 */
	function __toString()
	{
		return print_r($this->getVars(), true);
	}

	/**
	 * Enhanced settype that deals with JSON decoding and string escaping
	 *
	 * @param mixed $var  value
	 * @param string $type cast type, uses all valid PHP casts, plus 'esc' and 'json'
	 *
	 * @return mixed    cast value.
	 * @todo make this deal with Arrays?
	 */
	private function settype($var, $type='string')
	{
		if ($args[0] == 'esc')
		{
			settype($val, 'string');
			$val = $this->dbEscape($val);
		}
		elseif ($args[0] == 'json')
		{
			$val = json_decode($val, true);
			if ($val === null)
			{
				$val = $args[1]; // cannot decode, so use default
			}
		}
		else
		{
			settype($val, $args[0]);
		}
		return $val;
	}

	/**
	 * Escapes string, but is not reliant on a database connection like mysql_real_escape_string is..
	 *
	 * @param string $inp value that needs escapin'
	 *
	 * @return string    escaped string, safe for SQL injection.
	 */
	function dbEscape($inp)
	{
		if(!empty($inp) && is_string($inp)) {
		    return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
		}
		return $inp;
	}

	 /**
	  * Alternative to the magic method approach.
	  *
	  * @param mixed $var  request key to type
	  * @param string $type cast type to use.
	  *
	  * @return mixed    cast variable.
	  */
	function get($var, $type='string')
	{
		return $this->$var($type, '');
	}

	/**
	 * used to check the existance of a request var.
	 *
	 * @param string $name property name
	 *
	 * @return boolean    true if property was passed in the request
	 */
	function hasVar($name)
	{
		/** @noinspection PhpDeprecationInspection */
		/** @noinspection PhpDeprecationInspection */
		return array_key_exists($name, $this->getVars());
	}

	/**
	 * Will merge the GET and POST variables, and in case of a JSON content type or PUT HTTP method detection it will merge in the decoded raw stream contents as well.
	 * In case of an XML content type, will return the SimpleXMLElement object loaded with the raw stream contents.
	 *
	 * @return array|SimpleXMLElement    request vars (GET/POST - no Cookies) or data from other complex data transfers
	 */
	public function getVars()
	{
		// if a raw stream transfer, grab the data accordingly. xml returns an XML object
		if($this->isXML())
		{
			return new SimpleXMLElement($this->_rawString);
		}
		$extra = array();
		// json decodes...
		if($this->isJSON())
		{
			if($json = json_decode($this->_rawString, true))
			{
				if(is_array($json))
				{
					$extra = $json;
				}
			}
			else // try regular post data...
			{
				parse_str($this->_rawString, $extra);
				if(is_array($extra) === false)
					$extra = array();
			}
		}
		// plain PUT method parses as a normal POST request
		else if($this->getMethod() === 'PUT')
		{
			parse_str($this->_rawString, $extra);
		}
		$vars = array_merge($_POST, $_GET, $extra);

		return $vars;
	}

	/** returns the last path we were on.
	 * @return string path
	 */
	public function getLastPage()
	{
		return $_SESSION[__CLASS__]['lastPage'];
	}

	/** walks through an array and encodes all strings as UTF-8
	 * used for XML and JSON encoding
	 * @param mixed $data
	 * @return mixed data
	 */

	private function utf8Encode($data) // -- It returns $dat encoded to UTF8
	{
		if (is_string($data))
		{
			return utf8_encode($data);
		}
		elseif(is_array($data))
		{
			foreach($data as $i => $d)
			{
				$data[$i] = $this->utf8Encode($d);
			}
			return $data;
		}

		return $data;
	}


	/**
	 * Safe way to grab referrer.
	 *
	 * @return string    referrer URL if it exists, empty string if not
	 */
	public function getReferer()
	{
		if (isset($_SERVER["HTTP_REFERER"]))
		{
			return $_SERVER["HTTP_REFERER"];
		}
		else
		{
			return '';
		}
	}


	/** Returns remote IP address taking HTTP_X_FORWARDED_FOR into account
	 * @return string dotted quad IP
	 */
	public function getIP()
	{
		if( isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] != '' )
		{
			return $_SERVER['HTTP_CLIENT_IP'];
		}
		else if(isset($_SERVER['HTTP_X_CLIENT_IP']) && $_SERVER['HTTP_X_CLIENT_IP'] != '')
		{
			return $_SERVER['HTTP_X_CLIENT_IP'];
		}
		elseif( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '' )
		{
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			$ip_t = explode( ',', $ip ); // comma delimited list of IPs?
			return $ip_t[0];
		}
		elseif( isset($_SERVER['REMOTE_ADDR']))
		{
			return $_SERVER['REMOTE_ADDR'];
		}
		else
		{
			return null;
		}
	}

	/** returns the type of request made. (GET, POST, etc..)
	 * @return null|string HTTP request method.
	 */
	public function getMethod()
	{
		if (isset($_SERVER['REQUEST_METHOD']))
		{
			return strtoupper($_SERVER['REQUEST_METHOD']); // always uppercase as per standard
		}
		else
		{
			return null;
		}
	}

	/**
	 * returns cookie value
	 *
	 * @param string $cookie Cookie name
	 *
	 * @return string    Value or empty string if cookie doesn't exist.
	 */
	public function getCookie($cookie)
	{
		if (isset($_COOKIE[$cookie]))
		{
			return $_COOKIE[$cookie];
		}
		else
		{
			return '';
		}
	}

	/**
	 * Check to see if the cookie monster has a cookie. YUM!
	 *
	 * @param string $cookie cookie name
	 *
	 * @return boolean    True if cookie is set.
	 */
	public function hasCookie($cookie)
	{
		if (isset($_COOKIE[$cookie]))
		{
			return true;
		}
		return false;
	}



	public function pathToArray($path)
	{
		$path = explode( '/', $path);

		array_shift($path); //get rid of initial blank value
		if ($path[0] == '')
		{
			// default path.
			$path[0] = 'index';
		}
		$path = array_filter($path, function($item)
			{
				return $item !== '';
			});

		return $path;
	}

	/** grabs request variables and forms the actual request out of it.
	 */
	private function sanitizeRequest()
	{


		// just in case something in the server is hosed,
		if (isset($_SERVER['REQUEST_URI']))
		{
			$_SERVER['REQUEST_URI'] = str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']); //strip query off if it exists

			$path = $this->pathToArray($_SERVER['REQUEST_URI']);
			$params = array();
			// now merge in rest of parameters.
			switch ($_SERVER['REQUEST_METHOD'])
			{
				case "GET":
					$params = array_merge($params, $_GET);
					break;
				case "POST":
					$params = array_merge($params, $_POST);
					// deal with file - only take one if there's more than one.
					if (count($_FILES) > 0)
					{
						foreach ($_FILES as $key => $file)
						{
							$params['uploadedFile'] = $file;
						}
					}
					break;
				case "PUT":
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					$mimetype = finfo_file($finfo, $this->_rawTempFileName);
					/**
					 * @todo Should provide mimetype error checking and reject files at the the http level instead of relying on the method
					 */
					// build a POST style file info array
					$params['uploadedFile'] = array(
						'name' => $path[count($path)-1], // should be last item in path
						'type' => $mimetype,
						'size' => isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : filesize($tempfile),
						'tmp_name' => $this->_rawTempFileName,
						'error' => UPLOAD_ERR_OK
					);
					break;
				case 'DELETE':
					break;
				// More non standard headers here.
				case "HEAD":
					/**
					 * @todo This should output buffer the get and just send the size For now just return with no error.
					 */
					break;
				case "OPTIONS":
					// This just is a discovery method, so output the help template.
					break;
				default:
					$this->site->error->Toss('invalid request type detected: '.$_SERVER['REQUEST_METHOD'], E_USER_WARNING);
					$this->throwError(501, "Request method not supported.");
					break;
			}

			$this->path = $path;
			$this->parameters = $params;
		}
	}

	/**
	 * Saves current request vars in a session for later recall.
	 * When recalling, we merge new vars over the top of saved ones, so any new request
	 * variables will take precidence.
	 *
	 * @param string $group a group name for the save, this allows multiple groups in the same app. Default is 'request'
	 *
	 * @return bool true
	 */
	public function saveRequest($group='request')
	{
		$_SESSION[$this->__CLASS__][$group]['POST'] = $_POST;
		$_SESSION[$this->__CLASS__][$group]['GET'] = $_GET;
		return true;
	}

	/**
	 * restores saved request info. If nothing has been saved, nothing is recalled.
	 *
	 * @param string $group a group name for the recall. Default is 'request'
	 *
	 * @return boolean    true if load is successful. False if no save information is available.
	 */
	public function loadRequest($group='request')
	{
		if (!isset($_SESSION[$this->__CLASS__][$group]['POST']) || !isset($_SESSION[$this->__CLASS__][$group]['GET']))
		{
			return false; // not found.
		}
		$_POST = array_merge_recursive($_SESSION[$this->__CLASS__][$group]['POST'], $_POST);
		$_GET = array_merge_recursive($_SESSION[$this->__CLASS__][$group]['GET'], $_GET);
		return true;
	}
}
