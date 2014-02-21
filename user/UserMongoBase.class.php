<?php
/**
 * User class.
 */
abstract class UserMongoBase extends MongoBase implements MongoUserInterface
{
	use UserBase;


	/**
	 * This only runs if the site is in debug mode. If it is, it'll add any checked paths to the auth_path table if they don't already exist
	 * Helps add stuff if you're developing.
	 *
	 * @param string $path path to check for
	 *
	 * @return null
	 */
	protected function checkAndSetPath($path)
	{
		$pathObj = new AuthPathMongo($this->site);
		if(substr($path, -1) !== '/')
		{
			$path = $path.'/';
		}
		if($path === '/')
		{
			return;
		}
		if(!$pathObj->findOne(array('path' => $path)))
		{
			$pathObj->set('path', $path);
			$pathObj->insert();
		}
	}

	/*
	 *check if a user is a member of a specific group, by either group name or id. Default is by ID
	 *@param string $group The designator, either group id or group name
	 *@param bool $isId Flag to look up by _id vs groupName. Default is _id
	 *@return bool
	 */
	public function isMemberOf($group, $isId= true)
	{
		if($isId)
		{
			foreach($this->get('groups') as $grp)
			{
				if((string)$grp->get('_id') == $group)
				{
					return true;
				}
			}
		}
		else
		{
			foreach($this->get('groups') as $grp)
			{
				if($grp->get('groupName') == $group)
				{
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Database authentication search for a particular user/pw combo.
	 */
	public function findUser($user, $pw = null)
	{
		// user/pw required
		$search = array($this->getLoginField() => array('$regex' => '^'.$user.'$', '$options' => 'i'));
		if($this->findOne($search)) // find user...
		{
			if(isset($pw))
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
			else
			{
				return $this->get();
			}
		}
		return false;
	}

	/**
	 * Returns an array of group objects this user belongs to
	 *
	 * @return array    Group objects
	 */
	public function getGroups()
	{
		$this->_groups = array();
		if($groups = $this->get('groups'))
		{
			foreach ($groups as $group)
			{
				$g = new UserGroupMongo($this->site);
				if ($g->findOne($group)) // should already be stored as an object of mongoId
				{
					$this->_groups[] = $g;
				}
			}
		}
		return $this->_groups;
	}

	 /**
     * Adds user to group if they don't already have membership.
     *
     * @param MongoId $groupId Group ID to add to
     *
     * @return   true
     */
    public function addGroup($groupId)
    {
        if (!in_array($groupId, $this->schemaValues['groups']))
        {
            $this->schemaValues['groups'][] = $groupId;
        }
        return true;
    }

    /**
     * Removes Group membership from user if a member.
     *
     * @param MongoId $groupId Group to remove membership from
     *
     * @return const    true
     */
    public function removeGroup($groupId)
    {
        $this->schemaValues['groups'] = array_values(array_diff($this->schemaValues['groups'], array($groupId)));
        return true;
    }
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
		return array_values(array_unique(array_merge($globalPerms, array_keys($this->globalPermissions))));
	}
	/**
	 * gets all the permissions this user has, merging global perms as well
	 */
	public function getPermissions()
	{
		if(isset($this->extended[$this->registry][__FUNCTION__]) === false)
		{
			// look for global perms in the paths collection
			$paths = new AuthPathMongo($this->site);
			$globalPerms = array();
			$list = $paths->find(array('globalAuth' => array('$exists' => true, '$ne'=>false)), array('path' => 1, 'globalAuth'=>1));
			foreach($list as $path)
			{
				$globalPerms[$path['path']] = $path['globalAuth'];
			}

			if ($this->isLoggedIn() === false || $this->isLoaded === false)
			{
				return $globalPerms; // only Global permissions
			}
			$this->extended[$this->registry][__FUNCTION__] = array();
			// loop through groups and merge in perms
			$groups = $this->getGroups();
			foreach ($groups as $g)
			{
				// merge in this group's permissions
				$this->extended[$this->registry][__FUNCTION__] = array_merge($this->extended[$this->registry][__FUNCTION__], $g->get('permissions'));
			}

			if(!$perms = $this->get('permissions'))
				$perms = array();
			 // last grab from user document, user level overrides group level
			 // order matters here ---- user should override group, global override all
			$this->extended[$this->registry][__FUNCTION__] = array_merge($this->extended[$this->registry][__FUNCTION__], $perms, $globalPerms, $this->globalPermissions);
		}
		return $this->extended[$this->registry][__FUNCTION__];
	}


	/**
	 * This will log you in as any user defined, storing your current login for when logging out of the designated user.
	 * @param int $userId The user id
	 * @return bool True on success, false if an error occurs
	 * @todo need to update login function so this works
	 */
	public function loginAs($userId)
	{
		// retain top level user
		if(isset($_SESSION[$this->__CLASS__]['previousUserIds']))
		{
			$curId = $_SESSION[$this->__CLASS__]['previousUserIds'];
		}
		// else this is top level user...
		else
		{
			$_SESSION[$this->__CLASS__]['previousUserIds'] = array();
			$curId = $this->get($this->primaryKey);
		}
		// fetch user to be used
		if($this->findOne(array($this->primaryKey => $userId)))
		{
			unset($_SESSION[$this->__CLASS__]);
			// store parent user in new session
			$_SESSION[$this->__CLASS__]['previousUserIds'][] = $curId;
			// login as new user...
			return $this->login();
		}
		return false;
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

interface MongoUserInterface
{
	function getLoginField();
	function getPasswordField();
	function getGroups();
}
