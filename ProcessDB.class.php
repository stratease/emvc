<?php
class ProcessDB extends SchemaBase
{
	public $primaryKey = 'processId';
	public $primaryTable = 'process';
	protected $onBeforeUpdate = array('_lastUp');
	protected $onBeforeInsert = array('_lastUp');

	protected function _lastUp()
	{
		$this->set('lastUpdate', gmdate('Y-m-d H:i:s'));
	}
}
