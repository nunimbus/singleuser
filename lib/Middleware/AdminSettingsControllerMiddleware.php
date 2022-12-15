<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Settings\Controller\AdminSettingsController;

class AdminSettingsControllerMiddleware extends MiddlewareConstructor {
	public function beforeController($controller, $methodName) {
		if (! $controller instanceof AdminSettingsController) {
			return;
		}

		// Block access to any admin sectionss
		if (
			\OC::$server->getUserSession()->isLoggedIn() &&
			! $this->isInstanceAdmin
		) {
			header("Location: /index.php/settings/user", 302);
			exit();
		}

		return;
	}
}