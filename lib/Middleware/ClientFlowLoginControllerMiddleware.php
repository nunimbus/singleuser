<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC\Core\Controller\ClientFlowLoginController;
use OCP\AppFramework\Http\Response;

class ClientFlowLoginControllerMiddleware extends MiddlewareConstructor {

	public function beforeController($controller, $methodName) {
		if (! $controller instanceof ClientFlowLoginController) {
			return;
		}

		// This simply removes the link from the mobile login page for logging in with an app password
		\OC::$server->getSession()->set('oauth.state', true);
		return;
	}
}