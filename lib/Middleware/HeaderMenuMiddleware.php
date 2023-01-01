<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OC;

class HeaderMenuMiddleware extends MiddlewareConstructor {
	public function afterController($controller, $methodName, Response $response): Response {

		// Adds the app store link to the header menu
		if ($response instanceof TemplateResponse) {
			OC::$server->get('OC\NavigationManager')->add([
				'type' => 'settings',
				'id' => 'core_apps',
				'order' => 5,
				'href' => OC::$server->getUrlGenerator()->linkToRoute('settings.AppSettings.viewApps'),
				'icon' => OC::$server->getUrlGenerator()->imagePath('settings', 'apps.svg'),
				'name' => OC::$server->getL10NFactory()->get('lib')->t('Apps'),
			]);
		}

		return $response;
	}
}