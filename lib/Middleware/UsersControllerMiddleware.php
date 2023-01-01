<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Settings\Controller\UsersController;

class UsersControllerMiddleware extends MiddlewareConstructor {

	public function afterController($controller, $methodName, $response){
		if (! $controller instanceof UsersController && $methodName != 'usersList') {
			return $response;
		}

		// Hide the 'user-' groups from the user manager sidebars
		$params = $response->getParams();
		foreach ($params['serverData']['groups'] as $key=>$group) {
			if (str_starts_with($group['id'], 'user-')) {
				unset($params['serverData']['groups'][$key]);
			}
		}

		$params['serverData']['groups'] = array_values($params['serverData']['groups']);
		$response->setParams($params);

		return $response;
	}

	public function beforeOutput($controller, $methodName, $output) {
		if (! $controller instanceof OCA\Settings\Controller\UsersController && $methodName != 'getUsersDetails') {
			return $output;
		}

		// Hide the 'user-' groups from the user manager user details
		$data = json_decode($output, true);

		foreach ($data['ocs']['data']['users'] as $userKey=>$user) {
			foreach ($user['groups'] as $groupKey=>$group) {
				if (str_starts_with($group, 'user-')) {
					unset($user['groups'][$groupKey]);
					$data['ocs']['data']['users'][$userKey]['groups'] = array_values($user['groups']);
				}
			}
		}

		return json_encode($data);
	}
}