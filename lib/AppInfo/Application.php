<?php
/**
 * @copyright Copyright (c) 2021, Andrew Summers
 *
 * @author Andrew Summers
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\SingleUser\AppInfo;

use OCP\IRequest;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Utility\IControllerMethodReflector;

// Needed to register middleware
use OC_App;
use OCP\AppFramework\QueryException;
use OC\AppFramework\DependencyInjection\DIContainer;

// Middleware
use OCA\SingleUser\Middleware\DomManipulationMiddleware;
use OCA\SingleUser\Middleware\AppSettingsControllerMiddleware;
use OCA\SingleUser\Middleware\PersonalSettingsControllerMiddleware;
use OCA\SingleUser\Middleware\ContactsMenuControllerMiddleware;
use OCA\SingleUser\Middleware\HeaderMenuMiddleware;
use OCA\SingleUser\Middleware\ControllerPermissionsMiddleware;
use OCA\SingleUser\Middleware\UsersControllerMiddleware;
use OCA\SingleUser\Middleware\ShareesAPIControllerMiddleware;
use OCA\SingleUser\Middleware\ClientFlowLoginControllerMiddleware;
use OCA\SingleUser\Middleware\PersonalControllerMiddleware;

// Events
//use OCP\User\Events\UserCreatedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\User\Events\UserDeletedEvent;

// Event listeners
//use OCA\SingleUser\Listener\UserCreatedListener;
use OCA\SingleUser\Listener\UserAddedListener;
use OCA\SingleUser\Listener\UserDeletedListener;

class Application extends App implements IBootstrap {

	public const APP_ID = 'singleuser';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$server = \OC::$server;

		// Registers middleware to all applications
		$allApps = OC_App::getEnabledApps();
		array_push($allApps, 'core');

		foreach (OC_App::getEnabledApps() as $appId) {
			if ($appId != self::APP_ID) {
				try {
					$appContainer = $server->getRegisteredAppContainer($appId);
				}
				catch (QueryException $e) {
					$server->registerAppContainer($appId, new DIContainer($appId));
					$appContainer = $server->getRegisteredAppContainer($appId);
				}

				$appContainer->registerService('SingleUser\ControllerPermissionsMiddleware', function($c){
					return new ControllerPermissionsMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});
				$appContainer->registerMiddleware('SingleUser\ControllerPermissionsMiddleware');

				$appContainer->registerService('SingleUser\DomManipulationMiddleware', function($c){
					return new DomManipulationMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});
				$appContainer->registerMiddleware('SingleUser\DomManipulationMiddleware');

				$appContainer->registerService('SingleUser\HeaderMenuMiddleware', function($c){
					return new HeaderMenuMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});
				$appContainer->registerMiddleware('SingleUser\HeaderMenuMiddleware');
			}
		}

		// Register middleware to the 'core' app
		$coreContainer = $server->get(\OC\Core\Application::class)->getContainer();

		$coreContainer->registerService('SingleUser\ControllerPermissionsMiddleware', function($c){
			return new ControllerPermissionsMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$coreContainer->registerMiddleware('SingleUser\ControllerPermissionsMiddleware');

		$coreContainer->registerService('SingleUser\DomManipulationMiddleware', function($c){
			return new DomManipulationMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$coreContainer->registerMiddleware('SingleUser\DomManipulationMiddleware');

		$coreContainer->registerService('SingleUser\ContactsMenuControllerMiddleware', function($c){
			return new ContactsMenuControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$coreContainer->registerMiddleware('SingleUser\ContactsMenuControllerMiddleware');

		$coreContainer->registerService('SingleUser\ClientFlowLoginControllerMiddleware', function($c){
			return new ClientFlowLoginControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$coreContainer->registerMiddleware('SingleUser\ClientFlowLoginControllerMiddleware');

		// Register middleware to the "settings" app
		try {
			$settingsContainer = $server->getRegisteredAppContainer('settings');
		}
		catch (QueryException $e) {
			$server->registerAppContainer('settings', new DIContainer('settings'));
			$settingsContainer = $server->getRegisteredAppContainer('settings');
		}

		$settingsContainer->registerService('SingleUser\UsersControllerMiddleware', function($c){
			return new UsersControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$settingsContainer->registerMiddleware('SingleUser\UsersControllerMiddleware');

		$settingsContainer->registerService('SingleUser\PersonalSettingsControllerMiddleware', function($c){
			return new PersonalSettingsControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$settingsContainer->registerMiddleware('SingleUser\PersonalSettingsControllerMiddleware');

		$settingsContainer->registerService('SingleUser\AppSettingsControllerMiddleware', function($c){
			return new AppSettingsControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$settingsContainer->registerMiddleware('SingleUser\AppSettingsControllerMiddleware');

		// Register middleware to the "provisioning_api" app
		try {
			$provisioningApiContainer = $server->getRegisteredAppContainer('provisioning_api');
		}
		catch (QueryException $e) {
			$server->registerAppContainer('provisioning_api', new DIContainer('provisioning_api'));
			$provisioningApiContainer = $server->getRegisteredAppContainer('provisioning_api');
		}

		$provisioningApiContainer->registerService('SingleUser\UsersControllerMiddleware', function($c){
			return new UsersControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$provisioningApiContainer->registerMiddleware('SingleUser\UsersControllerMiddleware');

		// Register middleware to the files_sharing app
		try {
			$filesSharingContainer = $server->getRegisteredAppContainer('files_sharing');
		}
		catch (QueryException $e) {
			$server->registerAppContainer('files_sharing', new DIContainer('files_sharing'));
			$filesSharingContainer = $server->getRegisteredAppContainer('files_sharing');
		}

		$filesSharingContainer->registerService('SingleUser\ShareesAPIControllerMiddleware', function($c){
			return new ShareesAPIControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$filesSharingContainer->registerMiddleware('SingleUser\ShareesAPIControllerMiddleware');

		// Register middleware to the privacy app
		try {
			$privacyContainer = $server->getRegisteredAppContainer('privacy');
		}
		catch (QueryException $e) {
			$server->registerAppContainer('privacy', new DIContainer('privacy'));
			$privacyContainer = $server->getRegisteredAppContainer('privacy');
		}

		$privacyContainer->registerService('SingleUser\PersonalControllerMiddleware', function($c){
			return new PersonalControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$privacyContainer->registerMiddleware('SingleUser\PersonalControllerMiddleware');
	}

	public function register(IRegistrationContext $context): void {
		//$context->registerEventListener(UserCreatedEvent::class, UserCreatedListener::class);
		$context->registerEventListener(UserAddedEvent::class, UserAddedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}