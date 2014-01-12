<?php
/**
 * "Auth.class.php" -  Handles Authorization paths. Extend this to use. Acceptable defaults are provided here.
 *
 * CREATE TABLE `auth` (
 *  `auth_path` text NOT NULL COMMENT 'unix style path',
 *  `label` tinytext,
 *  `last_update` datetime DEFAULT NULL,
 *  `skip_auth` tinyint(4) DEFAULT NULL,
 *  PRIMARY KEY (`auth_path`(250))
 * ) ENGINE=MyISAM DEFAULT CHARSET=utf8
 *
 *
 */



class AuthPathSchema extends SchemaBase
{

   protected $primaryKey = 'auth_path';
   public $primaryTable = 'auth';
   protected $user = null;

	protected function startup()
	{
		$this->onBeforeUpdate[] = 'updateLast';
		$this->onBeforeinsert[] = 'updateLast';
		return parent::startup();
	}

   protected function updateLast()
   {
		$this->set('last_update', date('Y-m-d H:i:s'));
   }

}
