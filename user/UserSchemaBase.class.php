<?php
/**
 * User class.
 */
abstract class UserSchemaBase extends SchemaBase implements SchemaUserInterface
{
	use UserBase;
	/**
	 * returns all possible paths, merging hard coded globals with db paths
	 *
	 * @return array    array of possible paths
	 */
	public function getAllPermissions()
	{
		// look for global perms in the paths collection
		$paths = new AuthPathMongo($this->site);
		$globalPerms = array();
		$list = $paths->find(array(), array('path' => 1));
		foreach($list as $path)
		{
			$globalPerms[] = $path['path'];
		}
		 // last grab from user document, user level overrides group level
		return array_merge($globalPerms, array_keys($this->globalPermissions));
	}
	/**
	 * gets all the permissions this user has, merging global perms as well
	 */
	public function getPermissions()
	{
		if(isset(self::$extended[$this->registry][__FUNCTION__]) === false)
		{
			if ($this->isLoggedIn() === false || $this->isLoaded === false)
			{
				return array(); // no perms
			}
			self::$extended[$this->registry][__FUNCTION__] = array();
			// loop through groups and merge in perms
			$groups = $this->getGroups();
			foreach ($groups as $g)
			{
				// merge in this group's permissions
				self::$extended[$this->registry][__FUNCTION__] = array_merge(self::$extended[$this->registry][__FUNCTION__], $g->get('permissions'));
			}
			// look for global perms in the paths collection
			$paths = new AuthPathMongo($this->site);
			$globalPerms = array();
			$list = $paths->find(array('globalAuth' => array('$ne'=>false)), array('path' => 1, 'globalAuth'=>1));
			foreach($list as $path)
			{
				$globalPerms[$path['path']] = $path['globalAuth'];
			}
			if(!$perms = $this->get('permissions'))
				$perms = array();
			 // last grab from user document, user level overrides group level
			 // order matters here ---- user should override group, global override all
			self::$extended[$this->registry][__FUNCTION__] = array_merge(self::$extended[$this->registry][__FUNCTION__], $perms, $globalPerms, $this->globalPermissions);
		}
		return self::$extended[$this->registry][__FUNCTION__];
	}


	/**
	 * returns true if user is member of specified group.
	 *
	 * @param string $group Group name (doesn't look by ID)
	 *
	 * @return bool    true if member
	 */
	public function isMemberOf($group)
	{
		$cursor = $this->site->db->query("SELECT * FROM user_group
										 WHERE group_name = '".$this->site->db->escape($group)."'
										 AND user_id = '".$this->get($this->primaryKey)."'");
		if ($cursor->num_rows > 0)
		{
			return true;
		}
		return false;
	}

	public function joinGroup($group)
	{
		// get Group ID
		$group = new Group($this->site);
		$group_user = new GroupUser($this->site);
		$group_user->set(array(
			'user_id'=>$this->get($this->primaryKey),
			'group_id'=>$group->get($group->primaryKey)
		));
		return $group_user->insert();
	}

	public function detachGroup($group)
	{
		// get Group ID
		$group = new Group($this->site);
		$group_user = new GroupUser($this->site);
		$group_user->select(array(
			'user_id'=>$this->get($this->primaryKey),
			'group_id'=>$group->get($group->primaryKey)
		));
		return $group_user->delete();
	}

	public function getGroups()
	{
		return array(); // todo add base groups here
	}

	protected function findUser($user, $pw)
	{
		// user/pw required
		if(isset($user)
		   && isset($pw))
		{
			$search = array($this->getLoginField() => $user);
			if($this->select($search)) // find user...
			{
				// then authenticate..
				$dbPW = $this->get($this->getPasswordField());
				if($this->encryptString($pw, substr($dbPW, 0, $this->saltDepth)) === $dbPW)
				{
					return $this->get();
				}
				else
				{
					$this->clear(); // clear out user data
				}
			}
		}
		return false;
	}

	public function checkAndSetPath()
	{
		return true; // TODO finish this....
	}
}


interface SchemaUserInterface
{
	function getLoginField();
	function getPasswordField();
	function getGroups();
}
