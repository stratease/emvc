<?php

interface APIControllerInterface
{
	public function init($path);

}
interface APIParentControllerInterface
{
	public function create($params);
	public function retrieve($params);
	public function update($srch, $vals);
	public function delete($srch);
}
abstract class APIControllerBase extends ControllerBase implements APIParentControllerInterface
{
	public $autoRender = false;
	public $dynamicPage = true;
	public $credentials = null;
	public $useAuth = true;
	public $model = null;
	public $userField = 'username';
	public $passwordField = 'password';
	protected $requestMethod = null;
	private $predefinedErrorBatchSize = 100;
	public $safe = true;
	protected $specialFlags = array('method');
	/**
	 * initialization of important data
	 */
	public function setup()
	{
		// some init vars
		// detect http method
		if($this->site->request->method)
			$this->requestMethod = strtoupper($this->site->request->method);
		else
			$this->requestMethod = $this->site->request->getMethod();
	}

	/**
	 * intended to assist in handling any translation of data coming into the model
	 * @param array $input The assoc array
	 * @return mixed The converted data, in a format ready for the model
	 */
	public function objectifyInput($input)
	{
		return $input;
	}
	/**
	 * Recursively scans an array and converts Mongo objects into single data points, i.e. strings
	 * @param array $output The data to be converted
	 * @return array The converted data, in a format ready for passing up to the view layer
	 */
	public function normalizeOutput($output)
	{
		if(is_array($output)) // iterate on each field
		{
			foreach($output as $k => $field)
			{
				$output[$k] = $this->normalizeOutput($field);
			}
		}
		else if(is_a($output, 'MongoDate')) // convert to an ISO date
		{
			return gmdate('c', $output->sec);
		}
		else if(is_a($output, 'MongoId')) // convert to a string
		{
			return (string)$output;
		}
		return $output;
	}

	// aww CRUD...
	/**
	 * Handles creation of a new resource item
	 * @param array $params The assoc array of fields to be put into the model
	 * @return mixed The converted output of the new model for handing off to the view layer, or false on failure
	 */
	public function create($params)
	{
		$m = $this->model;
		$m->set($this->objectifyInput($params));
		if($m->insert())
		{
			return $this->normalizeOutput($m->get());
		}
		return false; // application error creating data entry?
	}
	/**
	 * Handles retrieval of a model(s) based on the datatype passed. An array will assume a complex search returning the list of results. A string assumes the unique ID for a resource
	 * @param array|string $params The assoc array of search fields or string identifier
	 * @return mixed The converted output of the found model(s) for handing off to the view layer, or false on failure
	 */
	public function retrieve($params)
	{
		$m = $this->model;
		if(is_array($params)) // complex search ?
		{
			if($c = $m->find($this->objectifySearch($params)))
			{
				$o = array();
				foreach($c as $row)
				{
					$o[] = $this->normalizeOutput($row);
				}
				return $o;
			}
			return false; // error with mongo args?
		}
		elseif($obj = $this->findModel($params))
		{
			return $this->normalizeOutput($obj->get());
		}
		else
		{
			return array(); // nothing found
		}
	}
	/**
	 * Handles search / updates of a single resource item
	 * @param string $id The unique ID of the resource item
	 * @param array $vals The data to update
	 * @return mixed The converted output of the new model for handing off to the view layer, or false on failure
	 */
	public function update($id, $vals)
	{
		if($obj = $this->findModel($id))
		{
			$obj->set($this->objectifyInput($vals));
			$obj->update();
			if($obj->update())
			{
				return $this->normalizeOutput($obj->get());
			}
		}
		return false; // something bad happened
	}
	/**
	 **NOTE this method must be overwritten to allow for batch updates, as it requires very specific integration logic to separate the "search" from the "update".
	 * Handles search / updates of a batch of resources.
	 * @param array $params All the data passed, requiring separation of the update data points from the search data points.
	 * @return mixed The converted output of the new models for handing off to the view layer, or false on failure
	 */
	public function updateList($params)
	{
		$str = "API missing definition handler for bulk updates.";
		$this->_errorOut(11, $str);
		$this->site->error->toss($str);
		return false;
	}
	/**
	 * Deletes a single item
	 * @param string $id The unique ID of the resource item
	 * @return bool True on success, false on failure. No relevant data points to be retrieved here.
	 */
	public function delete($id)
	{
		if($obj = $this->findModel($id))
		{
			if($obj->remove())
			{
				return true;
			}
		}
		return false; // something bad happened
	}
	/**
	 * Deletes a list of resources based on the search param
	 * @param array $srch The search params passed on to the resource to do a delete operation.
	 * @return bool True on success, false on failure. No relevant data points to be retrieved here.
	 */
	public function deleteList($srch)
	{
		$m = $this->model;
		if($m->remove($this->objectifySearch($srch), array('justOne' => false)))
		{
			return true;
		}
		return false;
	}
	/**
	 * Authenticates request
	 * @return bool Result of authentication attempt. True if an authenticated request
	 */
	protected function auth()
	{
		if($this->credentials)
		{
			$vars = $this->site->request->getVars();
			if(empty($vars[$this->userField])
			   || empty($vars[$this->passwordField]))
			{
				$this->_errorOut(1, "Must supply a valid '".$this->userField."' and '".$this->passwordField."' parameter.");
				return false;
			}
			else
			{
				// @todo finish this section
				$this->_errorOut(99, 'TODO ! need to implement abstracted auth functionality');
				return false;
			}
		}
		else
		{
			$ms = 'Must define a "credentials" property.';
			$this->_errorOut(6, 'Improperly setup API. '.$ms);
			$this->site->error->toss($ms);
			return false;
		}
	}
	public function objectifySearch($input)
	{
		if(is_string($input))
		{
			return array('_id' => new MongoId($input));
		}
		else // complex search..
		{
			return $input; // @todo ... any more processing? look for special mongo operators and clean up?
		}
	}
	public function findModel($ident)
	{
		$model = new $this->model->__CLASS__($this->site);
		if($model->findOne($this->objectifySearch($ident)))
		{
			return $model;
		}
		else
		{
			return false;
		}
	}
	protected function handlePOST($path)
	{
		if(count($path) === 0) // create operation
		{
			if($res = $this->create($this->cleanParams($this->site->request->getVars())))
			{
				$this->responseOut($res);
			}
			else
			{
				$this->_errorOut(9, 'Create operation failed.');
			}
		}
		else if(count($path) === 1) // if POST it now implies a COPY operation!
		{
			if($model = $this->findModel($path[0]))
			{
				$params = $model->get();
				unset($params['_id']);
				if($res = $this->create($params))
				{
					$this->responseOut($res);
				}
				else
				{
					$this->_errorOut(9, 'Create operation failed.');
				}
			}
			else // problem if we tried to copy an item that doesn't exist...
			{
				$this->_errorOut(3, "Unable to locate that resource.");
			}
		}
		else
		{
			$this->_errorOut(8, "The copy / create operation must be in a valid resource structure.");
		}
	}
	protected function handleGET($path)
	{
		if(count($path) === 1)
		{
			$params = $path[0];
		}
		else
		{
			$params = $this->cleanParams($this->site->request->getVars());
		}
		if(($res = $this->retrieve($params)) !== false)
		{
			$this->responseOut($res);
		}
		else
		{
			$this->_errorOut(10, "Invalid retrieval operation.");
		}
	}
	protected function handlePUT($path)
	{
		// update ?
		if(count($path) === 1)
		{
			if($res = $this->update($path[0], $this->cleanParams($this->site->request->getVars())))
			{
				$this->responseOut($res);
			}
			else
			{
				$this->_errorOut(4, 'Error updating that resource.');
			}
		}
		else if(count($path) === 0) // bulk update
		{
			if($res = $this->updateList($this->cleanParams($this->site->request->getVars()))) // create multiple entries
			{
				$this->responseOut($res);
			}
			else
			{
				$this->_errorOut(5, 'Error creating that resource.');
			}
		}
		else
		{
			$this->_errorOut(8, "Operation not permitted.");
		}
	}
	protected function handleDELETE($path)
	{
		if(count($path) === 1) // delete a single guy
		{
			if($res = $this->delete($path[0]))
			{
				$this->responseOut($res);
			}
			else
			{
				$this->_errorOut(7, "Error deleting that resource.");
			}
		}
		else if($this->safe === false) // DELETE EM ALL!
		{
			if($res = $this->deleteList())
			{
				$this->responseOut($res);
			}
			else
			{
				$this->_errorOut(7, "Error deleting that resource.");
			}
		}
		else
		{
			$this->_errorOut(8, "Operation not permitted.");
		}
	}
	public function indexAction($path)
	{
		$this->init($path);
		$continue = true;
		// auth...
		if($this->useAuth)
		{
			if(!($continue = $this->auth()))
			{
				$this->_errorOut(1, "Must supply a valid '".$this->userField."' and '".$this->passwordField."' parameter.");
			}
		}
		if($continue)
		{
			// route to model
			if($this->model)
			{
				switch($this->requestMethod)
				{
					case 'POST':
						$this->handlePOST($path);
						break;
					case 'GET':
						$this->handleGET($path);
						break;
					case 'PUT':
						$this->handlePUT($path);
						break;
					case 'DELETE':
						$this->handleDELETE($path);
						break;
					default:
						$this->_errorOut(2, 'Invalid HTTP request method defined.');
						break;
				}
			}
			else
			{
				$msg = 'Must define a "model" property.';
				$this->_errorOut(6, 'Improperly setup API. '.$msg);
				$this->site->error->toss($msg);
			}
		}
	}
	protected function cleanParams($params)
	{
		// special flags removed..
		foreach($this->specialFlags as $flag)
		{
			unset($params[$flag]);
		}
		// remove user/pw
		if($this->useAuth)
		{
			unset($params[$this->userField], $params[$this->passwordField]);
		}
		return $params;
	}
	protected function errorOut($errorCode, $errorMessage, $HTTP_RESPONSE = null)
	{

		if($errorCode < $this->predefinedErrorBatchSize) // predefined internal error code batch
		{
			$er = "Application defined error code must be defined above the predefined error code batch. You must create your error code at ".$this->predefinedErrorBatchSize." or greater.";
			$this->_errorOut(6, "Improperly setup API. ".$er);
			$this->site->error->toss($er);
		}
		else
		{
			$this->_errorOut($errorCode, $errorMessage, $HTTP_RESPONSE);
		}
	}
	private function _errorOut($errorCode, $errorMessage, $HTTP_RESPONSE = null)
	{
		// find response code
		if($HTTP_RESPONSE === null)
		{
			switch($errorCode)
			{
				case 10:
				case 8:
					$HTTP_RESPONSE = 501;
					break;
				case 1:
					$HTTP_RESPONSE = 401;
					break;
				default:
					$HTTP_RESPONSE = 500;
					break;
			}
		}
		http_response_code($HTTP_RESPONSE);
		$this->output(array('success' => false, 'error' => $errorMessage, 'errorCode' => $errorCode, 'referenceURL' => 'N/A'));
	}
	protected function responseOut($response, $messages = array())
	{
		if(is_array($messages) == false)
			$messages = array($messages);
		$this->output(array('success' => true, 'response' => $response, 'messages' => $messages));
	}

	protected function output($d)
	{
		header('Content-Type: application/json');
		echo json_encode($d);
	}
}
