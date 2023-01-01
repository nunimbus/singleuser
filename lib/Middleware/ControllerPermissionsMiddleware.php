<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC\AppFramework\Middleware\MiddlewareDispatcher;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\AppFramework\Middleware\Security\SecurityMiddleware;
use OCA\Settings\Middleware\SubadminMiddleware;
use OC\AppFramework\Http\Request;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\App;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OC;

class ControllerPermissionsMiddleware extends MiddlewareConstructor {
	public function beforeController($controller, $methodName) {
	}

	public function afterException($controller, $methodName, \Exception $exception): Response {
		$routeName = OC::$server->getRequest()->getParams()['_route'];

		// Hide group names from being returned (this is called when loading OCA\Settings\Controller\AppSettingsController)
		// This largely prevents JS errors because something SHOULD be returned, but this controller was bypassed
		if (
			$routeName != 'ocs.provisioning_api.Groups.getGroups'
		) {
			throw $exception;
		}

		$response = array (
			'ocs' => array (
				'meta' => array (
					'status' => 'ok',
					'statuscode' => 200,
					'message' => 'OK',
				),
				'data' => array (
					'groups' => array(),
				),
			),
		);

		return new JSONResponse($response);
	}

	public function afterController($controller, $methodName, Response $response): Response {
		$routeName = OC::$server->getRequest()->getParams()['_route'];
		$routeParts = explode('.', $routeName);
		$appName = null;

		if (sizeof($routeParts) == 3) {
			$appName = $routeParts[0];
		}
		else if (sizeof($routeParts) == 4) {
			$appName = $routeParts[1];
		}
		else {
			return $response;
		}

		if (
			$this->isAdmin ||
			// These are the routes to permit despite user permissions
			(
				$routeName != 'settings.AppSettings.viewApps' &&
				$routeName != 'settings.AppSettings.listApps' &&
				$routeName != 'settings.AppSettings.listCategories' &&
				$routeName != 'settings.AppSettings.enableApps' &&
				$routeName != 'settings.AppSettings.disableApps'
			) ||
			// These are exceptions to the routes above. If these result in `true`, do not permit the user action
			(
				// Prevent API calls to enable/disable apps for different groups. When enabling/disabling apps, the array
				// size should be 0 because `groups` is cleared in AppSettingsControllerMiddleware
				(
					(isset(OC::$server->getRequest()->getParams()['groups']) || array_key_exists('groups', OC::$server->getRequest()->getParams())) &&
					sizeof(OC::$server->getRequest()->getParams()['groups']) != 0
				)
			) ||
			OC::$server->getRegisteredAppContainer($appName)->offsetExists('Bypass')
		) {
			return $response;
		}

		$this->rerunController($controller, $methodName, $routeName);
	}

	private function rerunController($controller, $methodName, $routeName) {
		$routeParts = explode('.', $routeName);
		$appName = null;

		if (sizeof($routeParts) == 3) {
			$appName = $routeParts[0];
		}
		else if (sizeof($routeParts) == 4) {
			$appName = $routeParts[1];
		}
		else {
			return $response;
		}

		$c = OC::$server->getRegisteredAppContainer($appName);
		$application = new App($this->getApplicationClass($appName));
		$container = $application->getContainer();

		$container->registerAlias('MiddlewareDispatcher', MiddlewareDispatcher::class);
		$container->registerService(MiddlewareDispatcher::class, function($c) {
			$dispatcher = new MiddlewareDispatcher();
			$middlewareDispatcher = OC::$server->getRegisteredAppContainer('settings')->get(MiddlewareDispatcher::class);
			$middlewares = $middlewareDispatcher->getMiddlewares();

			$securityMiddleware = new SecurityMiddleware(
				$c->offsetGet('Request'),
				$c->get(IControllerMethodReflector::class),
				$c->get(INavigationManager::class),
				$c->get(IURLGenerator::class),
				$c->offsetGet('Psr\Log\LoggerInterface'),
				$c->offsetGet('AppName'),
				true,
				true,
				true,
				OC::$server->getAppManager(),
				$c->offsetGet('OCP\IL10N'),
				$c->get('OC\Settings\AuthorizedGroupMapper'),
				OC::$server->get(IUserSession::class)
			);

			foreach ($middlewares as $key=>$middleware) {
				if ($middleware instanceof SecurityMiddleware) {
					$middlewares[$key] = $securityMiddleware;
				}
				else if (
					$middleware instanceof SubadminMiddleware ||
					$middleware instanceof $this
				) {
					unset($middlewares[$key]);
				}
			};

			foreach ($middlewares as $middleware) {
				$dispatcher->registerMiddleware($middleware);
			}

			return $dispatcher;
		});

		$controller = $container->get(get_class($controller));
		$dispatcher = $container->offsetGet('Dispatcher');
		$container->offsetSet('Bypass', true);
		\OC\AppFramework\App::main(get_class($controller), $methodName, $container, OC::$server->getRequest()->getParams());

		exit();
	}

	// Borrowed from OC\Route\Router
	private function getApplicationClass(string $appName) {
		$appNameSpace = \OCP\AppFramework\App::buildAppNamespace($appName);

		$applicationClassName = $appNameSpace . '\\AppInfo\\Application';
		return $applicationClassName;
	}
}