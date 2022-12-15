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
use OCA\SingleUser\Middleware\AdminSettingsControllerMiddleware;
use OCA\SingleUser\Middleware\ContactsMenuControllerMiddleware;
use OCA\SingleUser\Middleware\HeaderMenuMiddleware;
use OCA\SingleUser\Middleware\ControllerPermissionsMiddleware;
use OCA\SingleUser\Middleware\UsersControllerMiddleware;
use OCA\SingleUser\Middleware\ShareesAPIControllerMiddleware;
//use OCA\SingleUser\Middleware\PasswordsPageControllerMiddleware;

// Events
//use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCA\Files\Event\LoadSidebar;
//use OCA\Passwords\Events\PasswordRevision\BeforePasswordRevisionClonedEvent;

// Event listeners
//use OCA\SingleUser\Listener\UserCreatedListener;
use OCA\SingleUser\Listener\UserDeletedListener;
use OCA\SingleUser\Listener\LoadSidebarListener;
//use OCA\SingleUser\Listener\BeforePasswordRevisionClonedEventListener;

class Application extends App implements IBootstrap {

	public const APP_ID = 'singleuser';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$server = \OC::$server;

		// Registers middleware to all applications
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

		$settingsContainer->registerService('SingleUser\AdminSettingsControllerMiddleware', function($c){
			return new AdminSettingsControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$settingsContainer->registerMiddleware('SingleUser\AdminSettingsControllerMiddleware');

		$settingsContainer->registerService('SingleUser\AppSettingsControllerMiddleware', function($c){
			return new AppSettingsControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$settingsContainer->registerMiddleware('SingleUser\AppSettingsControllerMiddleware');

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

		// Register middleware to the "passwords" app
		//try {
		//	$passwordsContainer = $server->getRegisteredAppContainer('settings');
		//}
		//catch (QueryException $e) {
		//	$server->registerAppContainer('settings', new DIContainer('settings'));
		//	$passwordsContainer = $server->getRegisteredAppContainer('settings');
		//}
		//
		//$passwordsContainer->registerService('SingleUser\PasswordsPageControllerMiddleware', function($c){
		//	return new PasswordsPageControllerMiddleware(
		//		$c->get(IRequest::class),
		//		$c->get(IControllerMethodReflector::class)
		//	);
		//});
		//$passwordsContainer->registerMiddleware('SingleUser\PasswordsPageControllerMiddleware');
	}

	public function register(IRegistrationContext $context): void {
		//$context->registerEventListener(UserCreatedEvent::class, UserCreatedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		//$context->registerEventListener(BeforePasswordRevisionClonedEvent::class, BeforePasswordRevisionClonedEventListener::class);
	}

	public function boot(IBootContext $context): void {
		// When the app is disabled, clean up the `instance-admin` group
		// This has to be done in `boot` because `getGroupManager` cannot resolve groups till here
		$server = $context->getServerContainer();

		if (
			$server->getRequest()->getRequestUri() == '/index.php/settings/apps/disable' &&
			in_array(self::APP_ID, $server->getRequest()->getParams()['appIds'])
		) {
			$groupManager = $server->getGroupManager();

			if ($group = $server->getGroupManager()->get('instance-admin')) {
				$group->delete();
			}
		}
	}
}