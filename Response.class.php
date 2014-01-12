<?php
/** Response class deals with sending special messaging situations out.
 * This should deal with any non-straight text messaging through the browser.
 *
 * @todo improve email sender to deal with attachements and text alternatives.
 * @todo add mail list functionality
 *
 */
class Response
{

	private $site;
	public $mailer;
	function __construct($site)
	{
		$this->site = $site;
	}

	/**
	 * Returns true if the request was crossdomain JSONP
	 *
	 * @return boolean    true/false
	 */
	public function isCrossDomain()
	{
		if ( $this->site->request->callback('string', '') == 'json')
		{
			return true;
		}

		return false;
	}
	/**
	 * outputs data in JSON or JSONP format based on the existance of a _GET['callback'] var.
	 *
	 * @param bool success true if success response.
	 * @param mixed $mixed optional data to serialize and send.
	 *
	 * @return null    null
	 */
	public function sendData($success, $data=null, $msg=null)
	{
		$this->site->page->renderView = false; // json response
		$out = array('success'=>$success);
		if ($data !== null)
		{
			$out['data'] = $data;
		}

		$out['messages'] = $msg;

		if ( $this->isCrossDomain())
		{
			header('Content-Type: text/javascript');
			echo $this->site->request->callback('string')."(".json_encode($out).")";
		}
		else
		{
			header('Content-Type: application/json');
			echo json_encode($out);
		}
	}
}
