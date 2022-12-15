<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC\Core\Controller\ContactsMenuController;
use OCP\AppFramework\Http\Response;

class ContactsMenuControllerMiddleware extends MiddlewareConstructor {

	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof ContactsMenuController) {
			return $response;
		}

		// Hide local users from the contacts listing for non instance-admins
		if (
			$this->isAdmin &&
			! $this->isInstanceAdmin &&
			$methodName == 'index'
		) {
			$data = $response->getData();

			foreach ($data['contacts'] as $key=>$contact) {
				if ($data['contacts'][$key]->getProperty('isLocalSystemBook')) {
					unset($data['contacts'][$key]);
				}
			}

			$response->setData($data);
		}
		return $response;
	}
}