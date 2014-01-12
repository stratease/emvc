<?php
class UserGroupMongo extends MongoBase
{
    public $collection = 'userGroup';

	protected $setField = array('permissions' => 'decodeJsonString');
    protected $onBeforeInsert = array('beforeInsert');
    protected $onBeforeUpdate = array('beforeUpdate');

	public function getDefaults()
	{
		return array(
			'description'=>'',
			'active' => true,
			'createDate'=>'',
			'lastUpdate'=>'',
			'permissions' => array()
		);
	}
	protected function decodeJsonString($value)
	{
		if(is_string($value))
		{
			$value = json_decode($value, true);
		}
		return $value;
	}

    protected function beforeInsert()
    {
        $this->set('createDate', new MongoDate());
        $this->set('lastUpdate', new MongoDate());
    }

    protected function beforeUpdate()
    {
        $this->set('lastUpdate', new MongoDate());
    }
	/**
	 * returns array of users in this group
	 *
	 * @return array userID's (MongoId)
	 * @todo
	 */
	public function getUsers()
	{

	}

	public function addPermission($path, $perm = 'read')
	{
		if ($perm !== 'write')
		{
			$perm = 'read'; //enumerate so we don't get weird stuff
		}
		$perms = $this->get('permissions');
		$perms[$path] = $perm;
		return $this->set('permissions', $perms);
	}

	public function removePermission($path)
	{
		$perms = $this->get('permissions');
		unset($perms[$path]);
		return $this->set('permissions', $perms);
	}
}
