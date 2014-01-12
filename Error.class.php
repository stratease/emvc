<?php
	/** define some constants if needed because PHP is too old
*/
if (!defined('E_STRICT'))
{
	define('E_STRICT', 2048);
}
if (!defined('E_RECOVERABLE_ERROR'))
{
	define('E_RECOVERABLE_ERROR' , 4096);
}
if (!defined('E_DEPRECATED'))
{
	define('E_DEPRECATED', 8192);
}
if (!defined('E_USER_DEPRECATED'))
{
	define('E_USER_DEPRECATED', 16384);
}

class Error
{

	private $site;
	private $consoleLogMsg = '';
	/**
 *@var object singleton class instance holder
 */
	private static $instance;

/**
 * @var bitfield if the error matches this, we should email it.
 */
	public $reportLevel = E_ALL;

/**
 * @var bitfield don't crash on notices and warnings.
 */
	public $crashLevel = E_ERROR;

/**
 * @var int logs older than this are deleted by the garbage collector. (seconds)
 */
	public $maxLogAge = 259200; // 3 days

/**
 * @var string From email address.
 */
	public $emailFrom = 'error@server.com';
/**
 * @var string Subject line
 */
	public $emailSubject = 'Event Report: ';

	/**
	 * @var string file that diskLog() writes to.
	 */
	public $diskLogFile = '/tmp/diskFile.log';

	/**
	 * @var string logging server for GELF messages
	 */
	public $graylog2Server = 'mon.';
	/**
 * Error code to readable string mappings
 *
 * @var array
 */
	private $errorTypes = array (
		E_PARSE => 'Parsing Error',
		E_ALL => 'All errors occured at once',
		E_WARNING => 'Run-Time Warning',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_WARNING => 'Compile Warning',
		E_USER_WARNING => 'User Warning',
		E_ERROR => 'Fatal Run-Time Error',
		E_CORE_ERROR => 'Core Error',
		E_COMPILE_ERROR => 'Compile Error',
		E_USER_ERROR => 'User Error',
		E_DEPRECATED => 'Deprecated code detected',
		E_USER_DEPRECATED => 'Deprecated code detected',
		E_RECOVERABLE_ERROR => 'Recoverable error',
		E_NOTICE => 'Notice',
		E_USER_NOTICE => 'User Notice',
		E_STRICT => 'Strict Error',
	);

	protected $url = 'unknown';
	protected $host = 'unknown';

	function __construct($site, $takeOver = true)
	{
		$this->site = $site;
		if(isset($_SERVER['SERVER_NAME']))
		{
			$this->url = $_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
			$this->host = $_SERVER['SERVER_NAME'];
		}
		$this->level = error_reporting();
		// redefine error handler
		if($takeOver)
		{
			set_error_handler(array($this, 'handleError'));
			register_shutdown_function(array($this, 'shutdownFunction')); // needed to trap fatal errors
		}
		$this->emailFrom = 'errorreporting@'.$this->host;
	}

	/** __invoke is called when executing class as a function. (only works on php 5.3)
	* Here we use it to trigger an error easily:
	* <pre>
	* $err = new errorHandler();
	* $err('SOMETHING BAD HAPPENED', $some_var);
	* </pre>
	*
	* will throw the error 'SOMETHING BAD HAPPENED' and attach the value of $some_var to it.
	*/
	function __invoke()
	{
		if (func_num_args() == 0)
 		{
 			return false;
 		}
 		// generate string
 		$args = func_get_args();
 		$log_string = array_shift($args);

		$this->toss($log_string, E_USER_ERROR, $args);
		return true;
	}

	/** cleans up old log files
 * Shouldn't be used too often, just every once in a while.
 * @return array of files that were actually deleted.
 */
	public function garbageCollector()
	{
		$deleted = array();
		if ($this->logFolder == '' || $this->logPrefix == '')
		{
			// abort if either is empty, just to play it safe. Otherwise it might go nuts and burn down the shed.
			return;
		}
		$files = scandir( $this->logFolder );
		foreach ($files as $file)
		{
			if (is_file($this->logFolder.$file))
			{
				if (substr($file, 0, strlen($this->logPrefix)) == $this->logPrefix )
				{
					if (substr($file, strlen($this->logPrefix), -4 ) < date('Ymd', time() - $this->maxLogAge))
					{
						// file is old, delete it.
						unlink($this->logFolder.$file);
						$deleted[] = $this->logFolder.$file;
					}
				}
			}
		}
		return $deleted;
	}

/** Returns either the relevant line or the parent file
 * @return array trace of parent file. See debug_backtrace() in php docs.
 */
 	public function scanBacktrace($parent = false)
 	{
 		$trace = debug_backtrace();
		// ditch error class stuff
		foreach($trace as $i => $err)
		{
			if(isset($err['file'])
			   && substr($err['file'],-strlen(basename(__FILE__))) === basename(__FILE__))
				unset($trace[$i]);
		}
		$trace = array_values($trace);
		if (count($trace) > 0)
		{
			if($parent)
				$trace = array_pop($trace); // parent..
			else
				$trace = $trace[0]; // relevant line
		}

 		if (!isset($trace['file']))
 		{
 			$trace['file'] = __FILE__;
 		}
 		if (!isset($trace['line']))
 		{
 			$trace['line'] = __LINE__;
 		}
 		return $trace;
 	}

	private function formatEmailData($title, $content, $fontsize=12)
	{
		return "<p style='font-size: $fontsize px; font-weight: bold;'> $title :</p>
			<blockquote style='padding=5px; border-width: 1px; border-style: solid; border-color: #B2B2B2; background-color: #E1E1E1;'>
			<pre style='font-family: Consolas,\"Andale Mono WT\",\"Andale Mono\",\"Lucida Console\",Monaco,\"Courier New\",Courier,monospace;'>\n"
			. str_replace('=>', "<span style='color: #ff0000'>=></span>",
				  substr(print_r($content, true),-1024000)
			)
			."</pre></blockquote>\n";
	}
/** Email function
 * sends error email out..
 * return bool true if success.
 */
 	private function email()
 	{
 		if($this->site->config('error', 'email') === null)
 		{
 			// need at least a to address to send a message.
 			return false;
 		}
		else // lets get the to address
		{
			if(is_array($this->site->config('error','email')))
			{
				$to = implode(', ', $this->site->config('error', 'email'));
			}
			else
			{
				$to = $this->site->config('error', 'email');
			}
		}
 		// generate string
		$mail_string = "<html>\n<body>\n";
		$mail_string .= "<p><span style='font-size: 14px; font-weight: bold;'>Error:</span> ".$this->lastError['errorMsg']."</p>\n";
		$mail_string .= "<p> <span style='font-size: 12px; font-weight: bold;'>Time:</span> ".date('r')."</p>\n";
 		$mail_string .= "<p> <span style='font-size: 12px; font-weight: bold;'>Host:</span> ".$this->host."</p>\n";
		$mail_string .= "<p> <span style='font-size: 12px; font-weight: bold;'>File:</span> ".$this->lastError['file']."</p>\n";
		$mail_string .= "<p> <span style='font-size: 12px; font-weight: bold;'>Line:</span> ".$this->lastError['line']."</p><hr>\n";
		if (isset($_SESSION) && count($_SESSION) > 0)
		{
			$mail_string .= $this->formatEmailData('SESSION Data', $_SESSION);
		}
		if (isset($_GET) && count($_GET) > 0)
		{
			$mail_string .= $this->formatEmailData('GET Data', $_GET);
		}
		if (isset($_POST) && count($_POST) > 0)
		{
			$mail_string .= $this->formatEmailData('POST Data', $_POST);
		}
		if (isset($_SERVER) && count($_SERVER) > 0)
		{
			$mail_string .= $this->formatEmailData('SERVER Vars', $_SERVER);
		}
		if (isset($_COOKIE) && count($_COOKIE) > 0)
		{
			$mail_string .= $this->formatEmailData('Cookies', $_COOKIE);
		}

		// always send a trace back.
		$mail_string .= $this->formatEmailData('Traceback',$this->lastError['backTrace']);
		$mail_string .= "</body></html>";

		// To send the HTML mail we need to set the Content-type header.
		$headers = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
		$headers  .= "From: {$this->emailFrom}\r\n";

		// send the email
		mail($to, $this->emailSubject.' '.substr($this->lastError['errorMsg'],0, 50), $mail_string, $headers);
		return true;
 	}

	/** convert error level to string
	 * @param bitfield $level error_level bitmask
	 * @return string
	 */
 	private function stringify($level)
 	{
		if (isset($this->errorTypes[$level]))
		{
			return $this->errorTypes[$level];
		}
		else
		{
			return "";
		}
 	}

/** Logging function
 * logs error message to a file based on date
 */
 	public function log()
 	{

 	}


/** user function to manually throw an error
 * @param string error string
 * @param array other contextual clues
 */
 	public function toss($error, $level = E_USER_ERROR, $context=array())
 	{
 		$trace = $this->scanBacktrace(false);
 		if ($context != array())
		{
			$this->handleError($level, $error, $trace['file'], $trace['line'],  $context);
		}
		elseif (isset($trace['args']))
 		{
 			$this->handleError($level, $error, $trace['file'], $trace['line'],  $trace['args']);
 		}
 		else
 		{
 			$this->handleError($level, $error, $trace['file'], $trace['line'],  debug_backtrace());
 		}
 	}

/** shutdown function - used to trap fatal errors since newer versions of php don't support this with the error handler. Only works with php5.2+
*/
	function shutDownFunction() {
		if (function_exists('error_get_last')) {
			$error = error_get_last();
			if ($error === null)
			{
				return;
			}
			if ($error['type'] == E_ERROR || $error['type'] == E_CORE_ERROR || $error['type'] == E_COMPILE_ERROR  ) {
				//do your stuff
				$this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
			}
		}
	}

/** Internal function to handle errors - esentially grabs the error, and based on the error_type sends it in various directions.
 * @param int $error_type The first parameter, errno, contains the level of the error raised, as an integer.
 * @param string $error The second parameter, errstr, contains the error message, as a string.
 * @param string $file The third parameter is optional, errfile, which contains the filename that the error was raised in, as a string.
 * @param int $line The fourth parameter is optional, errline, which contains the line number the error was raised at, as an integer.
 * @param array $context The fifth parameter is optional, errcontext, which is an array that points to the active symbol table at the point the error occurred. In other words, errcontext will contain an array of every variable that existed in the scope the error was triggered in. User error handler must not modify error context.
 * @return bool either interrupts execution, or returns true.
 */
 	public function handleError($error_type, $error, $file='', $line=0,  $context='')
 	{
		$db = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		// ditch error handler stuff
		foreach($db as $i => $err)
		{
			if(isset($err['file'])
			   && substr($err['file'],-strlen(basename(__FILE__))) === basename(__FILE__))
				unset($db[$i]);
		}
		$db = array_values($db);
		if ($file == '' || $line == 0)
		{
			$file = $d['file'];
			$line = $d['line'];
		}
		$pTrace = $this->scanBacktrace(true);
		$this->lastError = array(
			'errorMsg'=>$this->stringify($error_type)." - ".$error,
			'parent' => $pTrace['file'],
			'parentLine' => $pTrace['line'],
			'file'=>$file,
			'line'=>$line,
			'backTrace'=>$db,
			'context'=>$context
		);
		if(ini_get('display_errors'))
		{
			if($this->site->isCLI() === false)
				echo "<pre>";
			echo "\n";
			echo "Error: ".$this->lastError['errorMsg']."\n";
			echo "Host: ".$this->host."\n";
			echo "Parent Script: ".$this->lastError['parent']."\n";
			echo "Parent Line: ".$this->lastError['parentLine']."\n";
			echo "File: ".$this->lastError['file']."\n";
			echo "Line: ".$this->lastError['line']."\n";
			echo "Trace: ".print_r($this->lastError['backTrace'], true);
			echo "\n";
			if($this->site->isCLI() === false)
				echo "</pre>";
		}
		$log_stuff = array(
				date('r'),
				"Host: ".$this->host,
				"File: ".$this->lastError['file']." - Line: ".$this->lastError['line'],
				"Parent: ".$this->lastError['parent']." - Line: ".$this->lastError['parentLine'],
				$this->lastError['errorMsg']
 			);
		error_log(implode("\t", $log_stuff)."\n", 0);
		$this->email();
		if (($error_type & $this->crashLevel) != 0
			|| $error_type == E_USER_ERROR)
		{
			// crashLevel means we should bail out.
			exit($error_type);
		}
		return true;
 	}



	function __destruct()
	{
		if ($this->consoleLogMsg != '')
		{
			echo "<script>";
			echo $this->consoleLogMsg;
			echo "</script>";
		}
	}
	/** outputs using javascript so the output appears in the firebug console.
 * @param mixed vars to print. Specify any number of paramters.
 * @return bool true if it output a message, false if we're not in debug mode and nothing was printed.
 */
 	public function consoleLog()
 	{

 		$this->consoleLogMsg .= "console.log('PHP:', ";
 		$args = func_get_args();
 		foreach ($args as $arg)
 		{
 			if (is_object($arg))
 			{
 				$this->consoleLogMsg .= "".json_encode(get_object_vars($arg)).", \n";
 			}
 			elseif (is_array($arg))
 			{
 				$this->consoleLogMsg .= "".json_encode($arg).", \n";
 			}
 			else
 			{
 				$this->consoleLogMsg .= "\"".$this->SanitizeForJS($arg)."\",";
 			}

 		}
 		$this->consoleLogMsg .= "'');";
		return true;
 	}

	public function diskLog()
	{
		$msg = '';
		$args = func_get_args();
		foreach ($args as $arg)
 		{
 			if (is_object($arg))
 			{
 				$msg .= var_dump(get_object_vars($arg))."\n";
 			}
 			elseif (is_array($arg))
 			{
 				$msg .= var_dump($arg,true)."\n";
 			}
 			else
 			{
				if (is_string($arg))
				{
					$msg .= "\"".$arg."\", \n";
				}
				else
				{
					$msg .= var_export($arg, true).", \n";
				}

 			}

 		}
		$log_stuff = array(
 			date('r'),
 			$this->host,
 			$msg
 			);

		error_log(implode("\t", $log_stuff)."\n", 3, $this->diskLogFile);
		return true;
	}

	public function gelfLog()
	{
		require_once('GELFMessage.php');
		require_once('GELFMessagePublisher.php');
		$args = func_get_args();

		$trace = debug_backtrace();

		$trace = array_shift($trace);


 		if (!isset($trace['file']))
 		{
 			$trace['file'] = __FILE__;
 		}
 		if (!isset($trace['line']))
 		{
 			$trace['line'] = __LINE__;
 		}
		$message = new GELFMessage();
		$message->setShortMessage($args[0]);
		$message->setFullMessage('');
		$message->setHost($this->host);
		$message->setLevel(GELFMessage::DEBUG);
		$message->setFile($trace['file']);
		$message->setLine($trace['line']);
		$message->setFacility('PHP');
		foreach ($args as $key =>$arg)
		{
			if (!is_string($arg))
			{
				$arg = var_export($arg, true);
			}
			$message->setAdditional("Arg ".$key, $arg);
		}


		$publisher = new GELFMessagePublisher($this->graylog2Server);
		$publisher->publish($message);
		return true;
	}
/** takes provided string and converts low-order characters to javascript hex notation.
 * @param string string to sanitize
 * @return sanitized string.
 */
 	private function sanitizeForJS($str)
 	{
 		$new_str = '';
		for($i = 0; $i < strlen($str); $i++) {
			$val = ord(substr($str, $i, 1));
			$prefix = $val < 16 ? '\\x0' : '\\x';
			$new_str .= $prefix . dechex($val);
		}
		return $new_str;
 	}
}
