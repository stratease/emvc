<?php
abstract class ControllerBase
{
	public $site = null;
	/**
	 * Flag to define whether this controller accepts dynamic urls. 404 authentication must be handled within this controller if turned on.
	 * Off by default
	 * @var bool Flag for dynamic url parser
	 */
	public $dynamicPage = false;
	/**
	 *@var bool Flag to disable/enable auto rendering of the template engine.
	 */
	public $autoRender = true;
	/**
	 *@var array Stores an assoc array of variables used in the template engine. You must name the index the same as the name of the variable used in the template.
	 */
	public $view = array();
	/**
	 *@var string Optional override for the view file. These are all relative to the viewPath defined in the config
	 */
	public $viewFile = null;
	function __construct($site)
	{
		$this->site = $site;
	}

	/**
	 * Called before the controller action method is fired. Useful for controller specific global setup steps.
	 */
	public function setup($pg){}
	/**
	 * Called after the controller action method is fired. Useful for controller specific global cleanup steps.
	 */
	public function shutdown($pg){}
}
