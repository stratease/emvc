<?php
abstract class PluginBase implements PluginInterface
{
	public $site;

	function __construct($site)
	{
		$this->site = $site;
	}

}

interface PluginInterface
{
	public function startup();
}
