<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Privacy\Controller\PersonalController;
use OCP\AppFramework\Http\Response;
use OC;

class PersonalControllerMiddleware extends MiddlewareConstructor {
	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof PersonalController) {
			return $response;
		}

		// It seems shady to be hiding the admin names from the privacy app, but a) disclosing admin usernames is a bad
		// idea, and b) the access to user data by admins is described in the site FAQ
		if ($methodName == 'getAdmins') {
			$response->setData(array());
		}

		return $response;
	}
}