<?php
class RestStore
{
	public $site = null;
	public $start = 0;
	protected $sortAsc = null;
	protected $sortDesc = null;
	public $end = null;
	public $rows = array();
	public $sort = null;
	public $totalCount = 0;
	public $query = null; // search params
	/**
	* @param Site $site The site object
	* @param array $options Rest options
	*/
	function __construct($site, $options = array())
	{
		$this->site = $site;
		// set options...
		foreach($options as $f => $v)
		{
			$this->$f = $v;
		}
		// parse the request headers
		$this->readHeaders();
	}

	public function readHeaders()
	{
		switch($this->site->request->getMethod())
		{
			case 'GET':
				$this->parseGet();
				break;
			case 'POST':
			case 'PUT':
			case 'DELETE':
		}
	}
	/**
	 *
	 * Attempts to server the http headers with the collected results. If the rows array is populated it will return whatever has been populated in there.
	 * Note, that when the rows property is set the totalCount property should be set as well in order for the correct header information to be output.
	 * @param string $id If available, specify the primary key value being sent in this request.
	 */
	public function serve($id = null)
	{
		switch($this->site->request->getMethod())
		{
			case 'POST':
				echo json_encode($this->create($this->site->request->getVars()));
				break;
			case 'PUT':
				echo json_encode($this->update($id, $this->site->request->getVars()));
				break;
			case 'DELETE':
				$s = false;
				if($id !== null)
				{
					$s = $this->delete($id);
				}
				echo json_encode($s);
				break;
			case 'GET': // retrieve
				if($id !== null)
				{
					echo json_encode($this->retrieve($id));
					break;
				}
			default:
				// custom fetch ?
				if(isset($this->fetch))
					$this->rows = call_user_func_array($this->fetch, array());
				 // else we got rows externally...
				header('Content-Range: items '.$this->start.'-'.($this->start + count($this->rows)).'/'.$this->totalCount);
				echo json_encode($this->rows);
				break;
		}
	}
	/**
	* parses for the headers  - can't use the built in APACHE functions, due to using nginx
	* @todo This belongs in an HTTP class? php blows with standardizing this crap...
	*/
	public function getHeaders()
	{
		$h = array();
		foreach($_SERVER as $k => $v)
		{
			if(substr($k,0,5) === 'HTTP_') // I think these are the headers?
			{
				$h[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k,5)))))] = $v;
			}
		}
		return $h;
	}
	/**
	 * parses the get request
	 */
	protected function parseGet()
	{
		// request headers has ranges, ...
		$headers = $this->getHeaders();
		if(isset($headers['Range']))
		{
			// chop off the "items=" text ( redundant? ), and split on the "1-234" numeric format
			$range = explode("-", substr($headers['Range'], 6));
			$this->start = $range[0];
			$this->end = $range[1];
		}
		$vars = $this->site->request->getVars();
		// search for sort
		foreach($vars as $k => $val)
		{
			if(substr($k, 0, 5) === 'sort(') // dojos implementation of a rest sort.... pos
			{
				$sorts = explode(",", substr(substr($k, 5), 0, -1));
				$this->sort = array();
				foreach($sorts as $s)
				{
					// asc or desc?
					switch(substr($s,0,1))
					{
						case '_':
						case '+':
							$this->sort[substr($s,1)] = $this->sortAsc; // use pre-defined flag for sort
							break;
						case '-':
							$this->sort[substr($s,1)] = $this->sortDesc; // use pre-defined flag for sort
							break;
					}
				}
				unset($vars[$k]);
			}
		}
		// is there an explicit query json obj?
		if(isset($vars['query']))
		{
			if(!($this->query = json_decode($vars['query'], true)))
			{
				$this->query = $vars['query'];
			}
		}
		else // assume it's just a series of post vars
		{
			$this->query = $vars;
		}
	}
}
