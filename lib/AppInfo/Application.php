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

use OCA\SingleUser\Listener\UserCreatedListener;
use OCA\SingleUser\Listener\UserDeletedListener;
use OCA\SingleUser\Listener\LoadSidebarListener;
use OCA\SingleUser\Listener\BeforePasswordRevisionClonedEventListener;

use OCA\SingleUser\Middleware\DomManipulationMiddleware;
use OCA\SingleUser\Middleware\AppSettingsControllerMiddleware;
use OCA\SingleUser\Middleware\PersonalSettingsControllerMiddleware;
use OCA\SingleUser\Middleware\ContactsMenuControllerMiddleware;
use OCA\SingleUser\Middleware\HeaderMenuMiddleware;
use OCA\SingleUser\Middleware\ControllerPermissionsMiddleware;
use OCA\SingleUser\Middleware\PasswordsPageControllerMiddleware;

use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Passwords\Events\PasswordRevision\BeforePasswordRevisionClonedEvent;

// Needed to register middleware
use OC_App;
use OCP\AppFramework\QueryException;
use OC\AppFramework\DependencyInjection\DIContainer;

class Application extends App implements IBootstrap {

	public const APP_ID = 'singleuser';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		// Block from https://localhost:3443/index.php/settings/users
//		register_shutdown_function(function () {
//			Log the execution time for the user
//		});

		$server = \OC::$server;

		// Set up the `instance-admin` group
		if ($server->getAppConfig()->getValue(self::APP_ID, 'enabled') == 'no') {
			$groupManager = $server->getGroupManager();
			$group = $server->getGroupManager()->createGroup('instance-admin');

			if ($server->getUserSession()->isLoggedIn()) {
				$user = $server->getUserSession()->getUser();
				$userUID = $user->getUID();

				if ($groupManager->isInGroup($userUID, 'admin')) {
					$group->addUser($user);
				}
			}
			else {
				foreach ($groupManager->displayNamesInGroup('admin') as $userUID=>$displayName) {
					$userManager = $server->getUserManager();
					$user = $userManager->get($userUID);
					$group->addUser($user);
				}
			}
		}

		// Register middleware to the 'core' app
		$coreContainer = $server->query(\OC\Core\Application::class)->getContainer();

		$coreContainer->registerService('DomManipulationMiddleware', function($c){
			return new DomManipulationMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$coreContainer->registerMiddleware('DomManipulationMiddleware');

		$coreContainer->registerService('ContactsMenuControllerMiddleware', function($c){
			return new ContactsMenuControllerMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class)
			);
		});
		$coreContainer->registerMiddleware('ContactsMenuControllerMiddleware');

		// Registers the middleware to all applications
		foreach (OC_App::getEnabledApps() as $appId) {
			if ($appId != self::APP_ID) {
				try {
					$appContainer = $server->getRegisteredAppContainer($appId);
				}
				catch (QueryException $e) {
					$server->registerAppContainer($appId, new DIContainer($appId));
					$appContainer = $server->getRegisteredAppContainer($appId);	
				}

				$appContainer->registerService('AppSettingsControllerMiddleware', function($c){
					return new AppSettingsControllerMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});		
				$appContainer->registerMiddleware('AppSettingsControllerMiddleware');

				$appContainer->registerService('ControllerPermissionsMiddleware', function($c){
					return new ControllerPermissionsMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});
				$appContainer->registerMiddleware('ControllerPermissionsMiddleware');

				$appContainer->registerService('DomManipulationMiddleware', function($c){
					return new DomManipulationMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});
				$appContainer->registerMiddleware('DomManipulationMiddleware');
		
				$appContainer->registerService('HeaderMenuMiddleware', function($c){
					return new HeaderMenuMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});
				$appContainer->registerMiddleware('HeaderMenuMiddleware');

				$appContainer->registerService('PersonalSettingsControllerMiddleware', function($c){
					return new PersonalSettingsControllerMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});		
				$appContainer->registerMiddleware('PersonalSettingsControllerMiddleware');

				$appContainer->registerService('PasswordsPageControllerMiddleware', function($c){
					return new PasswordsPageControllerMiddleware(
						$c->get(IRequest::class),
						$c->get(IControllerMethodReflector::class)
					);
				});		
				$appContainer->registerMiddleware('PasswordsPageControllerMiddleware');
			}
		}
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(UserCreatedEvent::class, UserCreatedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		$context->registerEventListener(BeforePasswordRevisionClonedEvent::class, BeforePasswordRevisionClonedEventListener::class);
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

			$i = 1;
		}
	}
}
