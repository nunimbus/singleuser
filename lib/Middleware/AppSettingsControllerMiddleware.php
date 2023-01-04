<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Settings\Controller\AppSettingsController;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\IOutput;
use OC\AppFramework\DependencyInjection\DIContainer;
use OC_App;
use OC;

class AppSettingsControllerMiddleware extends MiddlewareConstructor {

	// TODO: Make this editable via the UI
	public const BLOCKED_APPS = array(
		'drop_account',
		'twofactor_totp',
		'bruteforcesettings',
		'contactsinteraction',
		'logreader',
		'privacy',
		'recommendations',
		'related_resources',
		'serverinfo',
		'support',
		'survey_client',
		'text',
		'updatenotification',
		'user_status',
	);

	public function beforeOutput($controller, $methodName, $output) {
		return $output;
	}

	public function beforeController($controller, $methodName) {
		if (! $controller instanceof AppSettingsController) {
			return;
		}

		if (! $this->isAdmin) {
			try {
				$container = OC::$server->getRegisteredAppContainer('OCA\Settings\AppInfo\Application');

				if (! $container->offsetExists('Bypass')) {
					return;
				}
			} catch (\Throwable $th) {
				return;
			}
		}

		if ($methodName == 'enableApps') {
			$this->toggleApp(true, $this->userUID);
		}
		else if ($methodName == 'disableApps') {
			$this->toggleApp(false, $this->userUID);
		}
	}

	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof AppSettingsController) {
			return $response;
		}

		if (! $this->isAdmin) {
			try {
				$container = OC::$server->getRegisteredAppContainer('OCA\Settings\AppInfo\Application');

				if (! $container->offsetExists('Bypass')) {
					return $response;
				}
			} catch (\Throwable $th) {
				return $response;
			}
		}

		$ocApp = new OC_App();
		// TODO: Make this editable via the UI
		// Remove unused categories <- This is being done in the Docker build
		// Add a "Protected" category to the app list for the admins
		if ($methodName == 'listCategories') {
			$data = $response->getData();

			$installedCategories = [];
			$installedApps = array_column($ocApp->listAllApps(), 'id');

			foreach ($installedApps as $app) {
				$appInfo = OC::$server->getAppManager()->getAppInfo($app);

				if (
					is_array($appInfo) && (
						isset($appInfo['category']) ||
						array_key_exists('category', $appInfo)
					)
				) {
					$installedCategories = array_merge(
						$installedCategories,
						(array) $appInfo['category']
					);
				}
			}

			$installedCategories = array_unique($installedCategories);
			$installedCategories = array_flip($installedCategories);

			foreach ($data as $key=>$category) {
				if (!(
					isset($installedCategories[$category['id']]) ||
					array_key_exists($category['id'], $installedCategories)
				)) {
					unset($data[$key]);
				}
			}

			$data = array_values($data);

			if ($this->isAdmin) {
				$protected = [
					'id'			=> 'protected',
					'ident'			=> 'protected',
					'displayName'	=> 'Protected',
				];

				array_push($data, $protected);
			}

			$response->setData($data);
		}

		// Only list apps that are installed; force current version (prevent upgrades) <- This is being done in the Docker build
		// When loading the app manager, sort or hide all apps that are of a protected type (i.e. cannot be limited to specific groups)
		if ($methodName == 'listApps') {
			$data = $response->getData();
			$installedApps = array_column($ocApp->listAllApps(), 'id');
			$installedApps = array_flip($installedApps);

			foreach ($data['apps'] as $key=>$app) {
				// Restricted types: filesystem, prelogin, authentication, logging, prevent_group_restriction
				if (OC::$server->getAppManager()->hasProtectedAppType($data['apps'][$key]['types'])) {
					// If the user is not an admin, hide all protected apps
					if (! $this->isAdmin) {
						unset($data['apps'][$key]);
						continue;
					}

					// If the user IS an admin, add protected apps to the "protected" category
					else {
						$data['apps'][$key]['name'] = "\u{1F512}" . $data['apps'][$key]['name'];
						$data['apps'][$key]['appstore'] = 'true';

						if (is_array($data['apps'][$key]['category'])) {
							array_push($data['apps'][$key]['category'], 'protected');
						}
						else {
							$data['apps'][$key]['category'] = array(
								$data['apps'][$key]['category'],
								'protected',
							);
						}
					}
				}

				// Prevents apps from being removed (assumes this is a Docker container with all apps preinstalled)
				$data['apps'][$key]['removable'] = false;
				$data['apps'][$key]['canUnInstall'] = false;

				if (! $this->isAdmin) {
					// Hide manually-defined hidden apps (set above before foreach loop)
					if (in_array($data['apps'][$key]['id'], self::BLOCKED_APPS)) {
						unset($data['apps'][$key]);
						continue;
					}

					// Mark apps as disabled if they are not enabled for their group
					if ($data['apps'][$key]['active']) {
						$groupIds = array_flip($data['apps'][$key]['groups']);
						if (sizeof($groupIds) != 0 && ! isset($groupIds['user-' . $this->userUID])) {
							$data['apps'][$key]['active'] = false;
						}
					}

					// Hide the "Limit app to groups" checkbox. The API method itself is blocked in ControllerPermissionsMiddleware
					if (! in_array('prevent_group_restriction', $data['apps'][$key]['types'])) {
						array_push( $data['apps'][$key]['types'], 'prevent_group_restriction');
					}

					// Hide any user group names to prevent privacy leaks
					$data['apps'][$key]['groups'] = array();
				}
			}

			$data['apps'] = array_values($data['apps']);
			$response->setData($data);
		}

		return $response;
	}

	// Assumes all apps are already downloaded by the admin and on the system
	public function toggleApp($enable, $userUID, $appIdArg = null) {
		// This allows the function to be called by internal scripts (specifically, `OCA\SingleUser\Listener\UserAddedListener`)
		if (! is_null($appIdArg)) {
			$app = $appIdArg;
		}
		else {
			$params = OC::$server->getRequest()->getParams();
			$app = $params['appIds'][0];
		}

		$appTypes = OC_App::getAppInfo($app)['types'];

		if (
			(isset($params['groups']) || array_key_exists('groups', $params)) &&
			! $this->isAdmin
		) {
			$i = 1;
		}

		if (! OC::$server->getAppManager()->hasProtectedAppType($appTypes) || in_array($app, self::BLOCKED_APPS)) {
			$userGID = OC::$server->getGroupManager()->get('user-' . $userUID)->getGID();

			// This will return 'yes', 'no', or a JSON array of group IDs for which the app is enabled
			$enabled = OC::$server->getConfig()->getAppValue($app, 'enabled', 'no');
			$groupIds = null;
			if ($enabled === 'yes') {
				$active = true;
			} elseif ($enabled === 'no') {
				$active = false;
				$groupIds = array($userGID);
			} else {
				$active = true;
				$groupIds = json_decode($enabled);
			}

			// This allows admins to enable/disable apps by group from the drop-down
			if (
				$enabled !== "no" && 
				$this->isAdmin &&
				(isset($params['groups']) || array_key_exists('groups', $params))
			) {
				if (sizeof($params['groups']) == 0) {
					OC::$server->getAppManager()->disableApp($app);
				}
				else {
					$groups = array_map(function($GID) { return OC::$server->getGroupManager()->get($GID);}, $params['groups']);
					OC::$server->getAppManager()->enableAppForGroups($app, $groups);
				}
			}
			// This handles any clicks made to the enable/disable buttons - app should be toggled for the `user-` groups
			else {
				$userGroups = OC::$server->getGroupManager()->search("user-");
				$allUsers = OC::$server->getGroupManager()->search("");

				// App is currently active and enabled for all users
				if (! is_array($groupIds)) {
					// A single user is disabling the app
					if ($enable == false) {
						$groupIds = array_map(function($GID) { return $GID->getGID();}, $allUsers);
					}
					// The admin has requested to limit the app to a single group
					else {
						$groupIds = array($userGID);
					}
				}

				// The logic excludes the current user's group if the requested action is disabling the app; otherwise, the
				// user group object is added in the `if` statement
				if ($active && $groupIds != array($userGID)) {
					if ($enable) {
						array_push($groupIds, $userGID);
					}
					else {
						if (($key = array_search($userGID, $groupIds)) !== false) {
							unset($groupIds[$key]);
						}
					}
				}
				// App is currently disabled and a request was made to disable the app for a user. Without this, the app
				// winds up getting enabled by accident
				else if ($active && $groupIds == array($userGID)) {
					$groupIds = array();
				}

				// Convert the list of group IDs into group objects
				$onlyUserGroups = true;
				$groups = array_filter($allUsers, function($group) use ($groupIds, &$onlyUserGroups) {
					$GID = $group->getGID();

					foreach ($groupIds as $groupId) {
						if ($GID == $groupId) {
							if (! str_starts_with($GID, 'user-')) {
								$onlyUserGroups = false;
							}
							return $group;
						}
					}
				});

				$groups = array_values($groups);

				// App is currently enabled and limited to all groups/users except the current user; the current user is
				// requesting to enable the app, so just enable it for everyone
				if (
					$onlyUserGroups &&
					is_array($groupIds) &&
					sizeof($groups) == sizeof($userGroups) &&
					$active &&
					$enable
				) {
					OC::$server->getAppManager()->enableApp($app);
				}
				// These should literally not be possible - call was made to disable an app, but $active = false?
				else if (! is_array($groupIds) && ! $active) {
					exit();
				}

				// Let the controller go ahead and disable the app for all users
				else if (sizeof($groups) == 0 && ! $enable) {
					return;
				}
				else {
					// Regardless of $enable == true/false, this will enable the app for the correct subset of users. This is
					// due to the block above with the comment "Convert the list of group IDs into group objects"
					OC::$server->getAppManager()->enableAppForGroups($app, $groups);
				}
			}

			if (is_null($appIdArg)) {
//				if ($this->isAdmin) {
//					$protocol = OC::$server->getRequest()->getServerProtocol();
//					$host = OC::$server->getRequest()->getServerHost();
//
//					$uri = OC::$server->getRequest()->getRequestUri();
//					$uriParts = explode('/', $uri);
//					array_pop($uriParts);
//					$uri = implode('/', $uriParts);
//
//					flush();
//					$io->setHeader("Location: $uri/installed/$app", 302);
//					$io->setHttpResponseCode(302);
//
//					$i = 1;
//				}

				if ($enable) {
					$data = array(
						'data' => array(
							'update_required' => false,
						),
					);
				}
				else {
					$data = array();
				}

				try {
					$container = OC::$server->getRegisteredAppContainer('OCA\Settings\AppInfo\Application');
				}
				catch (\Throwable $e) {
					OC::$server->registerAppContainer('settings', new DIContainer('OCA\Settings\AppInfo\Application'));
					$container = OC::$server->getRegisteredAppContainer('OCA\Settings\AppInfo\Application');
				}

				$io = $container[IOutput::class];
				$response = new JSONResponse($data);
				$output = $response->render();
				$io->setHeader('Content-Length: ' . strlen($output));
				$io->setOutput($output);

				exit;
			}
		}
		// This allows the admin to enable/disable protected apps
		else if ($this->isAdmin) {
			return;
		}
		// Throw an error - protected app (heck, the app should be hidden . . . )
		else {
			throw new \Exception();
		}
	}
}