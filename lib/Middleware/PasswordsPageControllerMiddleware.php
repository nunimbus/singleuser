<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Passwords\Controller\PageController;

class PasswordsPageControllerMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		if (! $controller instanceof PageController && $methodName != 'index') {
			return $output;
		}

//		$output = preg_replace('#/apps/passwords/js/Static/app.js#', '/apps/singleuser/js/Static/app.js', $output);

		return $output;
	}
}
