<?php
class _MessageItem
{
	public $message;
	public $level;
	/**
	* @param $message
	* @param $level
	*/
	function __construct($message, $level)
	{
		$this->message = $message;
		$this->level = $level;
	}
}