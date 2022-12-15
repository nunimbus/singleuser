<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCP\Route\IRouter;
use OC;

class ControllerPermissionsMiddleware extends MiddlewareConstructor {//implements IRouter {
	private $router;
	private $routes = array();
	private $routeNames = array();

/*	public function __construct() {
		$this->router = OC::$server->getRouter();
		$this->routes['routes'] = array();
	}

	public function getRoutingFiles() {
		return $this->router->getRoutingFiles();
	}

	public function loadRoutes($app = null) {
		$this->router->loadRoutes($app);
	}

	public function useCollection($name) {
		$this->router->useCollection($name);
	}

	public function getCurrentCollection() {
		return $this->router->getCurrentCollection();
	}

	public function create($name, $pattern, array $defaults = [], array $requirements = []) {
		array_push($this->routes['routes'], array(
			'name' => $name,
		));

		array_push($this->routeNames, $name);
		return $this->router->create($name, $pattern, $defaults, $requirements);
	}

	public function findMatchingRoute(string $url): array {
		return $this->router->findMatchingRoute($url);
	}

	public function match($url) {
		$this->router->match($url);
	}

	public function getGenerator() {
		return $this->router->getGenerator();
	}

	public function generate($name, $parameters = [], $absolute = false) {
		return $this->router->generate($name, $parameters, $absolute);
	}
*/
	public function beforeController($controller, $methodName) {
/*		$route = OC::$server->getRequest()->getParams()['_route'];

		$routingFiles = OC::$server->getRouter()->getRoutingFiles();

		foreach ($routingFiles as $appName=>$file) {
			$routes = include $file;
			$newRoutes = array();

			if (is_array($routes)) {
				foreach ($routes as $key=>$type) {
					$newRoutes[$key] = array($appName => $routes[$key]);
				}
				$this->routes = array_merge_recursive($newRoutes, $this->routes);
			}

			if (is_array($routes)) {
				foreach ($routes as $type=>$route) {
					if ($type == 'resources' || $type == 'ocs-resources') continue;
					foreach ($route as $el) {
						$routeParts = str_replace('#', '.', $el['name']);
						$routeName = "$appName.$routeParts";

						if ($type != 'routes') {
							$routeName = "$type.$routeName";
						}
						array_push($this->routeNames, $routeName);

						if (isset($el['postfix']) || array_key_exists('postfix', $el)) {
							array_push($this->routeNames, $routeName . $el['postfix']);
						}
					}
				}
			}
			$i = 1;
		}

		$this->routeNames = array_unique($this->routeNames);
		sort($this->routeNames);

		$currentRoute = OC::$server->getRequest()->getParams()['_route'];
		if (! in_array($currentRoute, $this->routeNames)) {
			$thisIsABigProblem = 1;
		}
		*/

		$allowedMethods = array(
			'viewApps',//settings.AppSettings.viewApps
			'listCategories',//settings.AppSettings.listCategories
			'listApps',//settings.AppSettings.listApps
			'enableApps',//settings.AppSettings.enableApps
			'disableApps',//settings.AppSettings.disableApps
			'getCss',
			'getStylesheet',
			'getSvgFromCore',
			'getThemedIcon',
			'getSvgFromApp',
		);

		$disallowedMethods = array(
			'usersList',
		);

		$filteredMethods = array(
			'editUser' => [
				'.*Scope' => ['*'],
				'role' => ['*'],
			],
		);

		$params = OC::$server->getRequest()->getParams();
		$currentRoute = OC::$server->getRequest()->getParams()['_route'];
		$methodName = $methodName;

		if ($this->userUID && !$this->isInstanceAdmin) {
			if ($this->reflector->hasAnnotation('AuthorizedAdminSetting')) {
				$i = 1;
			}
			else if ($this->reflector->hasAnnotation('PasswordConfirmationRequired')) {
				$i = 1;
			}
			else if ($this->reflector->hasAnnotation('SubAdminRequired')) {
				$i = 1;
			}
			else if ($this->reflector->hasAnnotation('NoAdminRequired')) {
				$i = 1;
			}
			else if ($this->reflector->hasAnnotation('PublicPage')) {
				$i = 1;
			}
			else if ($this->reflector->hasAnnotation('OnlyUnauthenticatedUsers')) {
				$i = 1;
			}
			else {
				$i = 1;
			}


			// If the function doesn't explicitly have the annotations of @NoAdminRequired or @PublicPage, assume it is
			// an admin page and block it unless otherwise allowed.
			if (!$this->reflector->hasAnnotation('NoAdminRequired') && !$this->reflector->hasAnnotation('PublicPage')) {
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

			// If the page is 
			if ($this->reflector->hasAnnotation('NoAdminRequired') || $this->reflector->hasAnnotation('PublicPage')) {
				if (isset($filteredMethods[$methodName]) || array_key_exists($methodName, $filteredMethods)) {
					$params = OC::$server->getRequest()->getParams();

					if (
						(isset($params['key']) || array_key_exists('key', $params)) &&
						(isset($params['value']) || array_key_exists('value', $params))
					) {
						foreach ($filteredMethods as $method=>$keys) {
							foreach ($keys as $key=>$values) {
								foreach ($values as $value) {
									if (
										($key == '*' || preg_match('#' . $key . '#', $params['key'])) &&
										($value == '*' || preg_match('#' . $value . '#', $params['value']))
									) {
										header("Location: /", 302);
										exit();			
									}
								}
							}
						}
					}
				}
			}
		}
	}
}