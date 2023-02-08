<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Files_Sharing\Controller\ShareesAPIController;
use OCA\Files_Sharing\Controller\ShareAPIController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;

class ShareesAPIControllerMiddleware extends MiddlewareConstructor {

	public function beforeController($controller, $methodName) {
		if (! $controller instanceof ShareAPIController) {
			return;
		}
		if ($methodName == 'createShare') {
			$server = \OC::$server;
			$params = $server->getRequest()->getParams();

			if (
				! $server->getUserManager()->userExists($params['shareWith']) &&
				str_contains($params['shareWith'], '@')
			) {
				$user = $server->getUserManager()->getByEmail($params['shareWith']);

				if (sizeof($user) != 1) {
					throw new \Exception('There should only be one user per email');
				}

				$attributes = array(array(
					'enabled' => true,
					'scope' => $params['path'],
					'key' => $server->getUserSession()->getUser()->getUid(),
					'sharedVia' => 'email',
					'shareWithLabel' => $params['shareWith'],
					'accepted' => false,
				));

				$defaults = array(
					'path' => null,
					'permissions' => null,
					'shareType' => -1,
					'shareWith' => null,
					'publicUpload' => 'false',
					'password' => '',
					'sendPasswordByTalk' => null,
					'expireDate' => '',
					'note' => '',
					'label' => '',
					'attributes' => null,
				);

				$params = array_filter($params, function($var){return $var !== null;} );
				$params['attributes'] = json_encode($attributes);
				$params['shareWith'] = $user[0]->getUid();
				$params = array_merge($defaults, $params);

				return $controller->createShare($params['path'],
					$params['permissions'],
					$params['shareType'],
					$params['shareWith'],
					$params['publicUpload'],
					$params['password'],
					$params['sendPasswordByTalk'],
					$params['expireDate'],
					$params['note'],
					$params['label'],
					$params['attributes'],
				);
			}
		}
	}

	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof ShareesAPIController) {
			return $response;
		}

		$params = \OC::$server->getRequest()->getParams();

		//$response = $controller->search($params['search'], $params['itemType'], 1, $params['perPage'], $params['shareType'], $params['lookup']);

		return $response;
	}

	public function beforeOutput($controller, $methodName, $output){
		if (! $controller instanceof ShareesAPIController) {
			return $output;
		}

		// Hide local users from the contacts listing for non-admins
		if (! $this->isAdmin) {
			$data = json_decode($output, true);

			//foreach ($data['contacts'] as $key=>$contact) {
			//	if ($data['contacts'][$key]->getProperty('isLocalSystemBook')) {
			//		unset($data['contacts'][$key]);
			//	}
			//}

			$params = \OC::$server->getRequest()->getParams();

			// Enforce exact user match
			foreach ($data['ocs']['data'] as $key=>$match) {
				if ($key != 'exact') {
					$data['ocs']['data'][$key] = array();
				}
			}

			// Force the display name of the user to match the search term used - don't disclose names, emails, or
			// usernames until the recipient opens the file
			foreach ($data['ocs']['data']['exact']['users'] as $key=>$user) {
				if ($data['ocs']['data']['exact']['users'][$key]['label'] == $params['search']) {
					unset($data['ocs']['data']['exact']['users'][$key]);
					continue;
				}

				$data['ocs']['data']['exact']['users'][$key]['label'] = $params['search'];
				$data['ocs']['data']['exact']['users'][$key]['value']['shareWith'] = $params['search'];
			}

			$data['ocs']['data']['exact']['users'] = array_values($data['ocs']['data']['exact']['users']);

			$output = json_encode($data);
		}
		return $output;
	}
}