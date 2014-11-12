<?php
class Page
{
	public $requestedPath = null;
	private $controllerParams = array();
	private $controllerMethod = 'indexAction';
	private $controller = null;
	private $site = null;

	function __construct($site, $requestedPath)
	{
		$this->site = $site;
		$this->requestedPath = $requestedPath;
	}

	public function loadController($controllerObj, $method = 'indexAction', $params = array())
	{
		$this->controller = $controllerObj;
		$this->controllerMethod = $method;
		$this->controllerParams = $params;
	}

	public function _404()
	{
		header("HTTP/1.0 404 Not Found");
		include(dirname(__FILE__).'/404.html');
		exit;
	}
	public function render()
	{
		// we are rendering now, so this is now the global page
		$this->site->page = $this;
		// if noone is subscribed, we want to output our own 404
		if(!$this->site->event->subscribers('404'))
		{
			$this->site->event->subscribe('404', array($this, '_404'));
		}
		if( $this->controller === null )
		{
			$this->site->event->publish('404');
		}
		else
		{
			if(ob_get_length() > 0)
			{
				ob_end_clean();
			}
			header("Connection: close");
			header("X-Powered-By: "); // Remove the header, don't need to advertise how out of date we are...
			//TODO: Figure out how to remove "Server" header also.. doesn't appear you can do this from inside php?
			ob_start();
			$this->site->event->publish('Page::onRender');
			call_user_func_array(array($this->controller, 'setup'), $this->controllerParams);
			call_user_func_array(array($this->controller, $this->controllerMethod), $this->controllerParams);
            // are we doing a json response? if not, we assume html...
            if($this->site->response->json === false
                && $this->site->webCatalog->isRedirect === false) {
                $viewFile = $this->controller->viewFile;
                if ($viewFile === null) // if not defined, auto grab em... @todo - this fails miserably when you're using dynamic paths. For now just set it in your controller. But it should be fixed.
                {
                    if ($this->requestedPath === '/' ||
                        $this->requestedPath === ''
                    ) {
                        $path = 'index';
                    } else {
                        if (strpos($this->requestedPath, '.')) {
                            $path = substr($this->requestedPath, 0, strpos($this->requestedPath, '.'));
                        } else {
                            $path = $this->requestedPath;
                        }
                        $path = trim($path, '/');
                    }
                    if ($ext = $this->site->config('template', 'extension')) {
                        $viewFile = $path . '.' . $ext;
                    } else {
                        $viewFile = $path . '.tpl';
                    }
                }
                if ($this->controller->autoRender === true) {
                    // do we have a view file?
                    if (is_string($this->site->config('site', 'viewPath'))) {
                        $viewPaths = [$this->site->config('site', 'viewPath')];
                    } else {
                        $viewPaths = $this->site->config('site', 'viewPath');
                    }
                    $_404 = true; // check if a valid template
                    foreach ($viewPaths as $i => $p) {
                        $viewPaths[$i] = $this->site->appFolder . $p;
                        if (is_file($viewPaths[$i] . DIRECTORY_SEPARATOR . $viewFile) === true) {
                            $_404 = false;
                        }
                    }
                    if ($_404) {
                        $this->site->event->publish('404');
                    } else {
                        switch ($this->site->config('template', 'engine')) {
                            case 'smarty':
                                // Summon Smarty Template Engine
                                $this->template = new Smarty();
                                $this->template->muteExpectedErrors(); // something is conflicting with our error class. See smarty docs for this.
                                // Absolute path to the root template dir
                                if ($this->site->config('site', 'viewPath')) {
                                    $this->template->setTemplateDir(realpath($this->site->appFolder . $this->site->config('site', 'viewPath')));
                                } else {
                                    $this->site->error->toss('[site] viewPath must be defined in your config!');
                                }
                                if ($this->site->config('smarty', 'configDir')) {
                                    $this->template->setConfigDir(realpath($this->site->appFolder . $this->site->config('smarty', 'configDir')));
                                }
                                // These directories need FULL WRITE ACCESS
                                // and could be stored outside of the web root for security purposes:
                                if ($this->site->config('smarty', 'compileDir')) {
                                    $this->template->setCompileDir(realpath($this->site->appFolder . $this->site->config('smarty', 'compileDir')));
                                }
                                if ($this->site->config('smarty', 'cacheDir')) {
                                    $this->template->setCacheDir(realpath($this->site->appFolder . $this->site->config('smarty', 'cacheDir')));
                                }
                                $this->site->event->publish('Page::SmartyPlugins');
                                // Assign template variables from the controller
                                if (isset($this->controller->view)) {
                                    foreach ($this->controller->view as $key => $value) {
                                        $this->template->assign($key, $value);
                                    }
                                }
                                // Finally, display the template...
                                try {
                                    $this->template->display($viewFile);
                                } catch (Exception $e) {
                                    $this->site->error->toss($e->getMessage(), E_USER_WARNING);
                                }
                                break;
                            case 'twig':
                                Twig_Autoloader::register();
                                if (!($paths = $this->site->config('twig', 'paths')))
                                    $paths = $viewPaths;
                                $loader = new Twig_Loader_Filesystem($paths);
                                // get twig options
                                $options = array('strict_variables' => ($this->site->config('site', 'debug')) ? true : false,
                                    'debug' => ($this->site->config('site', 'debug')) ? true : false);
                                if ($cacheDir = $this->site->config('twig', 'cacheDir')) {
                                    $options['cache'] = $this->site->appFolder . $cacheDir;
                                }
                                if (isset($options['debug']) === false) {
                                    $options['debug'] = (bool)$this->site->config('site', 'debug');
                                }
                                if (isset($options['strict_variables']) === false) {
                                    $options['strict_variables'] = (bool)$this->site->config('site', 'debug');
                                }
                                if ($this->site->config('twig', 'options')) {
                                    $options = array_merge($options, $this->site->config('twig', 'options'));
                                }
                                try {
                                    $twig = new Twig_Environment($loader, $options);
                                    $this->site->event->publish("Twig::Twig_Environment", $twig);
                                    echo $twig->render($viewFile, $this->controller->view);
                                } catch (Exception $e) {
                                    $this->site->error->toss($e->getMessage(), E_USER_WARNING);
                                    $this->site->event->publish('404');
                                }
                                break;
                            default:
                                $fname = $this->site->appFolder . $this->site->config('site', 'viewPath') . '/' . $viewFile;
                                if ($file = realpath($fname)) {
                                    require($file);
                                } else {
                                    $this->site->error->toss('Unable to auto render with view file: ' . $fname);
                                }
                                break;
                        }
                    }
                }
            }
			header("Content-Length: ".ob_get_length());
			ob_end_flush();
			flush();
			call_user_func_array(array($this->controller, 'shutdown'), $this->controllerParams);
			$this->site->event->publish('Page::afterRender');
		}
	}
}
