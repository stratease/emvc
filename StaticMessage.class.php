<?php
/**
 * Message Queue handler.
 */
class StaticMessage implements Iterator
{

	/** list position pointer */
	private $position = 0;
	/**
	 *@var string The id for this stream
	 */
	public $stream = '__default__';

	/**
	 *@var Site
	 */
	public $site = null;
	/**
	 *@var Array containts static messages
	 */
	protected static $queue = array();
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
		if(isset(self::$queue[$this->stream]) === false)
			self::$queue[$this->stream] = array();
		self::$queue[$this->stream][] = new _MessageItem($message, $level);
	}

	/**
	 * return number of items in queueu
	 *
	 * @return int    number of items in queue
	 */
	function count()
	{
		if(isset(self::$queue[$this->stream]))
			self::$queue[$this->stream] = array();
		return count(self::$queue[$this->stream]);
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
		$msg = self::$queue[$this->stream][$this->position];
		unset(self::$queue[$this->stream][$this->position]);
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
		return isset(self::$queue[$this->stream][$this->position]);
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
		if(isset(self::$queue[$this->stream]))
		{
			foreach(self::$queue[$this->stream] as $num => $message)
			{
				if ($message->level === $level)
				{
					$return[] = $message;
					if (!$keepMessages)
					{
						unset(self::$queue[$this->stream][$num]);
					}
				}
			}
			$this->rewind();
		}
		return $return;
	}
}
