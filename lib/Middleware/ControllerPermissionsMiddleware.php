<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCP\AppFramework\Http\Response;
use OC\Accounts\AccountManager;
use OCP\IRequest;
use OC_App;
use OC;

class ControllerPermissionsMiddleware extends MiddlewareConstructor {

	public function beforeController($controller, $methodName) {
		$allowedMethods = array(
			'viewApps',
			'listCategories',
			'listApps',
			'getCss',
			'getStylesheet',
			'getSvgFromCore',
			'getThemedIcon',
			'getSvgFromApp',
			'enableApps',
			'disableApps',
		);

		$disallowedMethods = array(
			'usersList',
		);

		if ($this->userUID && !$this->isInstanceAdmin) {
			if (!$this->reflector->hasAnnotation('NoAdminRequired')) {
				if (!in_array($methodName, $allowedMethods)) {
					header("Location: /", 302);
					exit();
				}
			}
			else {
				if (in_array($methodName, $disallowedMethods)) {
					header("Location: /", 302);
					exit();
				}
			}
		}
	}
}