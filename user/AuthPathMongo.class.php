<?php
/**
 * -  Handles Authorization paths. Extend this to use. Acceptable defaults are provided here.
 *
 *
 *
 */

class AuthPathMongo extends MongoBase
{
	public $collection = 'auth_path';
    public function getDefaults()
	{
		return array(
			"inNavigation"=> false,
			"label"=> "New Path",
			"parent"=>'',
			"path"=>'',
			"globalAuth"=> false // also could be 'read' or 'write'
		);
	}
}
