<?php
/**
 * Message Queue handler.
 */
class SessionMessage implements Iterator
{
	/** singleton static instance */
	private static $instance;
	/** pointer to the session var named in session_name */
	private $queue = array();
	/** list position pointer */
	private $position = 0;
	/** session key */
	private $session_name = '__message_queue';

	/**
	 *@var Site
	 */
	public $site = null;
	/**
	 * constructor - sets up pointer to session
	 */
	public function __construct($site, $options = array())
	{
		$this->site = $site;
		foreach($options as $f => $v)
		{
			$this->$f = $v;
		}
		if(session_id() === '') {
			session_start();
		}
		if(isset($_SESSION[$this->session_name]) === false
			|| is_array($_SESSION[$this->session_name]) === false)
		{
			$_SESSION[$this->session_name] = [];
		}
		if (!empty($_SESSION[$this->session_name]))
		{
			foreach($_SESSION[$this->session_name] as $key => $message)
			{
				if (!is_object ($message) && gettype ($message) == 'object')
				{
				   $_SESSION[$this->session_name][$key] = @unserialize (serialize (	$_SESSION[$this->session_name] ));
				}
			}
		}
	}

	/**
	 * appends a message to the queue.
	 *
	 * @param string $message message string
	 * @param string $level   Can be any arbitrary string, but recommend you stick to 'info', 'warning', or 'error'
	 *
	 * @return null
	 */
	function add($message, $level = 'notice')
	{
		$msg = new _MessageItem($message, $level);
		$_SESSION[$this->session_name][]  = $msg;
	}

	/**
	 * return number of items in queueu
	 *
	 * @return int    number of items in queue
	 */
	function count()
	{
		return count($_SESSION[$this->session_name]);
	}

	/**
	 * sets queue pointer back to the start of the queue
	 *
	 * @return null
	 */
	function rewind()
	{
		$this->position = 0;
	}

	/**
	 * returns current queue item. After you read a message with current (or foreach which calls current) the messages will dissapear from the queue.
	 *
	 * @return object  instance of message class.
	 */
	function current()
	{
		$msg = $_SESSION[$this->session_name][$this->position];
		unset($_SESSION[$this->session_name][$this->position]);
		return $msg;
	}

	/**
	 * returns the current pointer position
	 *
	 * @return int    position
	 */
	function key()
	{
		return $this->position;
	}

	/**
	 * advances pointer to next position
	 *
	 * @return null
	 */
	function next()
	{
		++$this->position;
	}

	/**
	 * checks if current position is valid.
	 *
	 * @return bool    true if valid
	 */
	function valid()
	{
		return isset($_SESSION[$this->session_name][$this->position]);
	}

	/**
	 * returns the next message in queue
	 *
	 * @return string    message instance
	 */
	function __toString()
	{
		if($this->valid() === true)
		{
			return $_SESSION[$this->session_name][$this->position]->__toString();
		}
		else
		{
			return '';
		}
	}

	/**
	 * Returns all messages of a particular level
	 * @param $level
	 * @param bool $keepMessages - Whether or not to keep messages in Queue
	 * @return array
	 */
	public function getByLevel($level, $keepMessages = false)
	{
		$return = array();
		foreach($_SESSION[$this->session_name] as $num => $message)
		{
			if ($message->level === $level)
			{
				$return[] = $message;
				if ($keepMessages === false)
				{
					unset($_SESSION[$this->session_name][$num]);
				}
			}
		}
		$this->rewind();
		return $return;
	}
}
