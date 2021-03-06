<?php
/**
 * Controller for Error Handling
 * 
 * @package ApplicationController
 * @subpackage ErrorController
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Error Controller Class
 * 
 * @package ApplicationController
 * @subpackage ErrorController
 */
class ErrorController extends Zend_Controller_Action {
	/**
	 * Error Controller Class
	 * Index Action - "http://SERVER/error/eror"
	 * calls the modle to select the requests to Transfer and 
	 * opens process for each one of the requests   
	 * 
	 * @package ApplicationController
	 * @subpackage ErrorController
	 */
	public function errorAction() {
		$errors = $this->_getParam('error_handler');

		if (!$errors || !$errors instanceof ArrayObject) {
			$this->view->message = 'You have reached the error page';
			return;
		}

		switch ($errors->type) {
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
				// 404 error -- controller or action not found
				$this->getResponse()->setHttpResponseCode(404);
				$priority = Zend_Log::NOTICE;
				$this->view->message = 'Page not found';
				break;
			default:
				// application error
				$this->getResponse()->setHttpResponseCode(500);
				$priority = Zend_Log::CRIT;
				$this->view->message = 'Application error';
				break;
		}

		// Log exception, if logger available
		if ($log = $this->getLog()) {
			$log->log($this->view->message, $priority, $errors->exception);
			$log->log('Request Parameters', $priority, $errors->request->getParams());
		}

		// conditionally display exceptions
		if ($this->getInvokeArg('displayExceptions') == true) {
			$this->view->exception = $errors->exception;
		}

		$this->view->request = $errors->request;
	}
	/**
	 * method to retreive log object
	 * 
	 * @return object log
	 */
	public function getLog() {
		$bootstrap = $this->getInvokeArg('bootstrap');
		if (!$bootstrap->hasResource('Log')) {
			return false;
		}
		$log = $bootstrap->getResource('Log');
		return $log;
	}

}

