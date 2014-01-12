<?php
/** Event Class

*/


class Event
{
	/**
	 * @var array registry of event listeners
	 *
	 * Format: 'event' => array(callback1, callback2, ...)
	 */
	private static $registry = array();

	function __construct($site)
	{
		$this->site = $site;
	}


/** __invoke is called when executing class as a function. (only works on php 5.3)
 * here we use it as an alias for publish()
 */
	function __invoke()
	{
 		$args = func_get_args();
 		return $this->publish($args[0], $args[1]);
	}

/** attaches a callback function to a specific event. If event doesn't exist yet, it'll create it.
 * @param string event name
 * @param mixed callback function.
 * @return boolean true if successful. Throws a warning and returns false if callback isn't found.
 */
	public function subscribe($event, $callback)
	{
		if (is_callable($callback))
		{
			self::$registry[$event][] = $callback;
			return true;
		}
		else
		{
			$this->site->error->toss('Callback is not valid: '.print_r($callback, true), E_USER_WARNING);
			return false;  // if the callback isn't correct, fail out.
		}
	}

/** detaches a callback function from a specific event.
 * @param string event name
 * @param mixed callback function.
 * @return boolean true
 */

	public function unsubscribe($event, $callback)
	{
		if (in_array($callback, self::$registry[$event]))
		{
			self::$registry[$event] = array_diff(self::$registry[$event], array($callback));
		}
		return true;
	}

/** executes all callbacks attached to specified function.
 * @param string event name
 * @param mixed parameter to pass to function.
 * @return boolean false if no callbacks are registered.
 */
	public function publish()
	{
		$args = func_get_args();
		if(isset(self::$registry[$args[0]]))
		{
			foreach(self::$registry[$args[0]] as $callback)
			{
				unset($args[0]);
				call_user_func_array($callback, $args);
			}
			return true;
		}
		else
		{
			return false;
		}
	}

/** List all callback functions associated with specified event.
 * @return boolean false if event not found.
 */
	public function subscribers($event)
	{
		if (isset(self::$registry[$event]))
		{
			return self::$registry[$event];
		}
		else
		{
			return false;
		}
	}


}
