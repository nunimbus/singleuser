<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Settings\Controller\UsersController;
use OCP\AppFramework\Http\Response;
use OC\Accounts\AccountManager;
use OCP\IRequest;
use OC_App;
use OC;

class UsersControllerMiddleware extends MiddlewareConstructor {

	public function afterController($controller, $methodName, $response){
		if (! $controller instanceof UsersController && $methodName != 'usersList') {
			return $response;
		}

		// Hide the 'admin-' groups from the user manager
		if ($this->isInstanceAdmin) {
			$params = $response->getParams();
			foreach ($params['serverData']['groups'] as $key=>$group) {
				if (str_starts_with($group['id'], 'admin-')) {
					unset($params['serverData']['groups'][$key]);
				}
			}

			array_values($params);
			$response->setParams($params);
		}
		
		return $response;
	}
}