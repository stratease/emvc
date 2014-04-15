<?php
/**
 * User class.
 */
trait UserBase
{
	protected $autoLogin = true;
	public $validPasswordRegex = '';
	protected $emailFieldName = '';
	protected $authenticationFieldName = '';
	protected $IPFieldName = '';
	protected $permissionsColumn = 'permissions';
	public $invalidPasswordMessage = '';
	protected $_messageQueue = null;
	protected $googleAuthorizeFieldMapping = array();
	protected $facebookAuthorizeFieldMapping = array();
	protected $externalAuthorizationType = null;
	protected $_externalAuthObj = null;
	protected $saltDepth = 15;
	protected $_validPassword = true;
	protected $errorMsg = '';
	protected $failedFields = array();
	/**
	 * @var array hard coded list of perms that everyone should have. useful for login/home/user account pages and the likes
	 */
	protected $globalPermissions = array();

	protected function startup()
	{
		$this->setField[$this->getPasswordField()] = 'setPassword';
		$this->onAfterUpdate = array('afterUpdate');
		return parent::startup();
	}

	protected function afterUpdate()
	{
		$this->registerSession();
	}
	/**
	 * gets the read/write/denied flag for a specific permission field.
	 * @param string $perm The permission
	 * @return mixed The type of access this user has
	 */
	public function getPermission($perm)
	{
		if ($this->get('root') === true)
		{
			return 'write';
		}
		$perms = $this->getPermissions();
		if(isset($perms[$perm]))
		{
			return $perms[$perm];
		}
		return false;
	}
	/**
	 * call this to find out if a user has any access to a path. You may specify a type, 'read' is default. Write implies read.
	 *
	 * @param string $path path to check
	 * @param string $perm type to check for. Default 'read'.
	 *
	 * @return Type    boolean true if user has permission
	 */
	public function hasAccess($path, $type='read')
	{
		$perms = $this->getPermissions();
		$this->checkAndSetPath($path);
		if ($this->get('root')===true)
		{
			return true;
		}
		$perms = $this->getPermissions();
		if(substr($path, -1) !== '/')
		{
			$path .= '/';
		}
		if (isset($perms[$path])
			&& ($perms[$path] == $type
				|| $perms[$path] == 'write')) // write access implies read
		{
			return true;
		}
		$this->errorMsg .= $path." is restricted.\n";
		return false;
	}
	/**
	 * determines current page from request object, and returns true if the user has permission to see it.
	 *
	 * @param string $type permission type
	 *
	 * @return bool    true if has permission
	 */
	public function hasPageAccess($type='read')
	{
		return $this->hasAccess($this->site->webCatalog->actualPath, $type);
	}
	protected function registerSession($force = false)
	{
		if($force === true || $this->isActiveSession() === true)
		{
			$primaryKey = $this->primaryKey;
			// verify you're registering the logged in user, or if logging in a user
			if(isset($_SESSION[$this->__CLASS__][$primaryKey]) === false || $this->get($primaryKey) === $_SESSION[$this->__CLASS__][$primaryKey])
			{
				$values = $this->get();
				$_SESSION[$this->__CLASS__] = $values;
			}
		}
	}
	protected function setPassword($value)
	{
		$this->_validPassword = $this->isValidPassword($value);
		return $this->encryptString($value);
	}
	/**
     * verifies required information in auth session
     *
     * @return boolean    true if valid
     */
    public function isValidSession()
    {
		if(session_id() === '')
		{
			session_start();
		}
        if (isset($_SESSION[$this->__CLASS__][$this->getLoginField()]) && isset($_SESSION[$this->__CLASS__][$this->getPasswordField()]))
        {
            return true;
        }
        return false;
    }

	public function isLoggedIn()
    {
        return $this->isValidSession();
    }
	public function isActiveSession()
	{
		if ($this->site->isCLI())
		{
			return false; // no sessions on command line runs.
		}
		if(session_id() === '')
			session_start();
		return (isset($_SESSION[$this->__CLASS__][$this->getLoginField()]));
	}

	public function isValidUsername($username, $checkDuplicate = false)
    {
        $bool = true;
		if($checkDuplicate === true)
		{
			$Class = $this->__CLASS__;
			$user = new $Class($this->site);
			if($user->findUser($username))
	        {
	            $bool = false;
				$this->errorMsg .= "Username already taken: ".$username."\n";
			}
		}
        return $bool;
    }

    public function isValidPassword($password)
    {
        $bool = true;
		if($this->validPasswordRegex !== '')
		{
			if(substr($this->validPasswordRegex,0, 1) === '/') // did we put in proper format?
				$regex = $this->validPasswordRegex;
			else
				$regex = '/'.$this->validPasswordRegex.'/';
		}
		else {
			$regex = '/.{8}/'; // default
		}

		// if we didnt match, it's an invalid password
		if(preg_match($regex, $password) == false)
		{
			if($this->invalidPasswordMessage === '')
				$this->errorMsg .= "Invalid password. Minimum of 8 characters required.\n";
			else
				$this->errorMsg .= $this->invalidPasswordMessage."\n";
			$bool = false;
		}
        return $bool;
    }
	protected function validateInsert()
	{
		$bool = true;
		$checkDb = true;
		$passwordField = $this->getPasswordField();
		$usernameField = $this->getLoginField();
		if($this->emailFieldName !== '')
		{
			$emailFieldName = $this->emailFieldName;
			if($this->isValidEmail($this->get($emailFieldName), $checkDb) === false)
			{
				$this->failedFields[] = $emailFieldName;
				$bool = false;
			}
			$checkDb = false;
		}
		if($this->_validPassword === false)
		{
			$this->failedFields[] = $passwordField;
			$bool = false;
		}
		if($this->isValidUsername($this->get($usernameField), $checkDb) === false)
		{
			$this->failedFields[] = $usernameField;
			$bool = false;
		}
		return $bool;
	}
	public function getInvalidFields()
	{
		return $this->failedFields;
	}
	public function isValidEmail($email, $checkDB = false)
    {
        $bool = true;
		if(!preg_match("/^\S+@\S+\.\S+$/", $email)) // TODO add a more RFC compliant validator...
		{
			$bool = false;
		}

        // failed email format validation
        if($bool === false)
		{
            $this->errorMsg .= "Invalid email format.\n";
		}
        // else check if email registered
        elseif($checkDB === true)
        {
            $Class = $this->__CLASS__;
			$user = new $Class($this->site);
			if($user->get($this->emailFieldName, $email) === true)
			{
                $bool = false;
                $this->errorMsg .= "A user with that email is already registered.\n";
            }
        }
		return $bool;
	}

	public function getByAuthenticationKey($key)
	{
		if($this->select(array($this->authenticationFieldName => substr($key, 0, 40), $this->primaryKey => substr($key, 40))))
		{
			$authField = $this->authenticationFieldName;
			$this->$authField = mt_rand(); // clear out
			return true;
		}
		else
		{
			return false;
		}
	}

	public function generateAuthenticationKey()
	{
		if($this->authenticationFieldName !== '')
		{
			$primaryKey = $this->primaryKey;
			if($this->$primaryKey !== '' && $this->$primaryKey !== null)
			{
				$authField = $this->authenticationFieldName;
				$key = sha1(uniqid('', true)); // 40 char
				$this->$authField = $key;
				return $key.$this->$primaryKey;
			}
			else
			{
				$this->error->toss(__METHOD__." Active instance must be loaded to uniquely associate to an entry.", E_USER_WARNING);
			}
		}
		else
		{
			$this->error->toss(__METHOD__." Authentication field must be defined.", E_USER_WARNING);
			return false;
		}
	}

	protected function encryptString($string, $salt = null)
	{
		if($salt === null)
		{
			$rand = rand(0, 12);
			$salt = substr(uniqid(uniqid(), true), $rand, $this->saltDepth); // saltDepth num of chars at random starting index
		}
		return $salt.sha1($salt.$string);
	}

	public function login($username = null, $password = null, $stayLoggedIn = 604800)
	{
        // attempt auto login via session/cookies
        if($username === null && $password === null)
        {
			// check if a valid session is set and the user isn't loaded yet
            if($this->isActiveSession() === true
			   && $this->isLoaded === false)
            {
				// verify account before continuing....
				if($this->loadRow($_SESSION[$this->__CLASS__]) === true // load first... then validate...
				   && $this->isValidAccount() === true)
				{
					// already logged in properly, no extra data needed so return
					return true;
				}
            }
			// if an instance has been loaded, force a login/register session vars
			// verify account before continuing....
			elseif($this->isLoaded === true
				   && $this->isValidAccount())
			{
				$this->registerSession(true);
				return true;
			}
			if($this->isValidAccount() === false) {
				$this->errorMsg .= "Your account is locked.\n";
			}
			// all else... invalid account, boot 'em!~!!!
			$this->logOut();
			return false;
        }
        // else attempt a specific login
        else if($row = call_user_func_array(array($this, 'findUser'), func_get_args()))
		{
			if($this->loadRow($row))
			{
				if($this->isValidAccount() === true)
				{
					if($this->IPFieldName !== '')
					{
						if($this->IPFieldName !== '' )
						{
							$this->set($this->IPFieldName, $this->site->request->getIP());
							$this->update();
						}
					}
					if(session_id() === '')
						session_start();
					if($this->autoLogin === true)
					{
						setcookie(session_name(), session_id(), time() + $stayLoggedIn, '/', $_SERVER['HTTP_HOST']); // TODO fix this... doesn't work in all browsers
					}
					$this->registerSession(true);
					return true;
				}
			}
			else
			{
				$this->errorMsg .= "An unknown error occurred.\n";
				$this->site->error->toss('User row failed to load properly.', E_USER_WARNING);
				// failed to load for some reason
				return false;
			}
		}
		else
		{
			$this->errorMsg .= "Your login or password provided does not match our records.\n";
			// failed to login, improper credentials provided
			return false;
		}
	}

	/**
	 * Retrieves the text error messages generated.
	 * @return string
	 */
	public function getError()
	{
		return $this->errorMsg;
	}

	public function setExternalAuthorization($type)
	{
		$this->externalAuthorizationType = $type;
		switch($this->externalAuthorizationType)
		{
			case 'google':
			    $this->_externalAuthObj = new LightOpenID($_SERVER['HTTP_HOST']);
				return true;
			case 'facebook':
				return true;
			default:
				$this->error->toss('Invalid External Authorization Type specified: '.$this->externalAuthorizationType, E_USER_WARNING);
				return false;
		}
	}

	public function getExternalAuthorizationUrl($returnUrl)
	{
		switch($this->externalAuthorizationType)
		{
			case 'google':
		        // request fields...
				$this->_externalAuthObj->returnUrl = $returnUrl;
				$this->_externalAuthObj->required = array_keys($this->googleAuthorizeFieldMapping);
				$this->_externalAuthObj->identity = 'https://www.google.com/accounts/o8/id';
		        return $this->_externalAuthObj->authUrl();
			case 'facebook':
				if(session_id() === '')
				{
					session_start();
				}
				$fields = $this->facebookAuthorizeFieldMapping;
				unset($fields['first_name']);
				unset($fields['last_name']);
				$fields = array_keys($fields);

				$_SESSION['FB_AUTH']['state'] = md5(uniqid(mt_rand(0, 99999), true)); //CSRF protection
				return "https://www.facebook.com/dialog/oauth?client_id=".$this->facebook_APP_ID."&state=".$_SESSION['FB_AUTH']['state']."&redirect_uri=".urlencode($returnUrl)."&scope=".implode(",", $fields);
			default:
				$this->error->toss('Invalid External Authorization Type specified: '.$this->externalAuthorizationType, E_USER_WARNING);
				return false;
		}
	}

	public function mapExternalAuthorizationFields()
	{
		switch($this->externalAuthorizationType)
		{
			case 'google':
				$return = false;
				if($values = $this->_externalAuthObj->getAttributes())
				{
					foreach($this->googleAuthorizeFieldMapping as $theirs => $ours)
					{
						if(isset($values[$theirs]))
						{
							$return = true;
							$this->$ours = $values[$theirs];
						}
					}
				}
				return $return;
			case 'facebook':
				$return = false;
				if($this->_externalAuthObj !== null)
				{
					foreach($this->facebookAuthorizeFieldMapping as $theirs => $ours)
					{
						if($this->_externalAuthObj->$theirs !== null)
						{
							$return = true;
							$this->$ours = $this->_externalAuthObj->$theirs;
						}
					}
				}
				return $return;
			default:
				$this->error->toss('Invalid External Authorization Type specified: '.$this->externalAuthorizationType, E_USER_WARNING);
				return false;
		}
	}

	public function isExternalAuthorizationValid($REQUEST)
	{
		switch($this->externalAuthorizationType)
		{
			case 'google':
				if($this->_externalAuthObj->mode === null || $this->_externalAuthObj->mode === 'cancel')
				{
					return false;
				}
				else
				{
					return $this->_externalAuthObj->validate();
				}
			case 'facebook':
				if(!empty($REQUEST['error']))
				{
					// catch error message here?
					//$REQUEST['error_reason']
					//$REQUEST['error_description']

					return false;
				}
				// code to authenticate and get access token with...
				elseif(isset($REQUEST['code']))
				{
					if(session_id() === '')
					{
						session_start();
					}

					// validate that this should match... (CSRF hacking..?)
					if($REQUEST['state'] === $_SESSION['FB_AUTH']['state'])
					{
						// url to get token...
						$token_url = "https://graph.facebook.com/oauth/access_token?client_id=" . $this->facebook_APP_ID;
						$token_url .= "&redirect_uri=".urlencode('http://'.$_SERVER['HTTP_HOST'].'/register.php?type='.$REQUEST['type'].'&action=externalRegister')."&client_secret=".$this->facebook_APP_SECRET ."&code=".$REQUEST['code'];

						// get token...
						$curl = curl_init();

						curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
						curl_setopt($curl, CURLOPT_SSLVERSION,3);
						curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
						curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
						curl_setopt($curl, CURLOPT_HEADER, false);
						curl_setopt($curl, CURLOPT_POST, false);
						curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($curl, CURLOPT_URL, $token_url);
						parse_str( curl_exec($curl), $params);

						// get user info...
						$graph_url = "https://graph.facebook.com/me?access_token=".$params['access_token'];
						curl_setopt($curl, CURLOPT_URL, $graph_url);

						// get user data
						if($this->_externalAuthObj = json_decode(curl_exec($curl)))
						{
							return true;
						}
						else
						{
							return false;
						}
					}
					//if not possibly being CSRF hacked...
					else
					{
						$this->error->toss("The state does not match. You may be a victim of CSRF.", E_USER_WARNING);
						return false;
					}
				}
				else
				{
					return false;
				}
			default:
				$this->error->toss('Invalid External Authorization Type specified: '.$this->externalAuthorizationType, E_USER_WARNING);
				return false;
		}
	}

	protected function isValidAccount()
	{
		return true;
	}

	public function logOut()
	{
        if(session_id() === '')
            session_start();
		setcookie(session_name(), session_id(), 1, '/', $_SERVER['HTTP_HOST']);
		unset($_COOKIE[session_name()]);
		unset($_SESSION[$this->__CLASS__]);
	}

}
