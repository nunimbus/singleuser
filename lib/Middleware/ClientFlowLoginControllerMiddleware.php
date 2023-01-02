<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC\Core\Controller\ClientFlowLoginController;
use OC\Core\Controller\ClientFlowLoginV2Controller;
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

	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof ClientFlowLoginV2Controller || $methodName != 'showAuthPickerPage') {
			return $response;
		}

		$params = $response->getParams();
		$params['oauthState'] = true;
		$response->setParams($params);
		return $response;
	}
}