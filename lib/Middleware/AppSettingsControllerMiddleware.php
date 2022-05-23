<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCP\AppFramework\Http\Response;
use OC\Accounts\AccountManager;
use OCA\Settings\Controller\AppSettingsController;
use OCP\IRequest;
use OC_App;
use OC;

class AppSettingsControllerMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		if (! $controller instanceof AppSettingsController) {
			return $output;
		}

		// Not instance admin and logged in
		if (OC::$server->getUserSession()->isLoggedIn() && ! $this->isInstanceAdmin) {
			$json = json_decode($output, true);

			if (is_array($json)) {
				if ($methodName == 'listApps') {
					foreach ($json['apps'] as $key=>$app) {
						// Prevents apps from being removed
						$json['apps'][$key]['removable'] = false;
						$json['apps'][$key]['canUnInstall'] = false;

						// Mark apps as disabled if they are not enabled for their group
						if ($json['apps'][$key]['active']) {
							$groupIds = array_flip($json['apps'][$key]['groups']);
							if (sizeof($groupIds) != 0 && ! isset($groupIds['admin-' . $this->userUID])) {
								$json['apps'][$key]['active'] = false;
							}
						}

						// Add a 'types' array to enabled apps (using 'filesystem' as a placeholder for no particular reason) - this hides the "Limit to groups" element from standard users
						if (sizeof($json['apps'][$key]['types']) == 0) {
							array_push($json['apps'][$key]['types'], 'filesystem');
						}
					}
					$output = json_encode($json);
				}

				return $output;
			}
		}
		return $output;
	}

	public function beforeController($controller, $methodName) {
		if (! $controller instanceof AppSettingsController) {
			return;
		}

		if ($this->isAdmin && ! $this->isInstanceAdmin) {
			if ($methodName == 'enableApps') {
				$this->toggleApp(true);
			}
			else if ($methodName == 'disableApps') {
				$this->toggleApp(false);		
			} 
		}
	}

	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof AppSettingsController) {
			return $response;
		}

		$ocApp = new OC_App();
/*		// Fix the broken CSP for app images (really should be moved to the appstore script, but it's not behaving)
		if ($methodName == 'viewApps') {
			$domains = array();

			foreach ($controller->listApps()->getData()['apps'] as $app) {
				if (
					OC::$server->getAppManager()->getAppInfo($app['id']) &&
					(
						isset(OC::$server->getAppManager()->getAppInfo($app['id'])['screenshot']) ||
						array_key_exists('screenshot', OC::$server->getAppManager()->getAppInfo($app['id']))
					)
				) {
					$screenshots = OC::$server->getAppManager()->getAppInfo($app['id'])['screenshot'];
					if (!is_array($screenshots)) {
						$screenshots = array($screenshots);
					}
					foreach ($screenshots as $screenshot) {
//						foreach ($screenshot['@attributes'] as $url) {
							$url = $screenshot;
							$url = filter_var(trim($url), FILTER_SANITIZE_URL);

							if (filter_var($url, FILTER_VALIDATE_URL)) {
								$parsed_url = parse_url(trim($url));
								array_push($domains, $parsed_url['scheme'] . '://' . $parsed_url['host']);
							}
						}
//					}
					if (isset($app['appstoreData']) || array_key_exists('appstoreData', $app)) {
						if ($app['appstoreData']['screenshots']) {
							foreach ($app['appstoreData']['screenshots'] as $screenshots) {
								foreach ($screenshots as $url) {
									$url = filter_var(trim($url), FILTER_SANITIZE_URL);

									if (filter_var($url, FILTER_VALIDATE_URL)) {
										$parsed_url = parse_url(trim($url));
										array_push($domains, $parsed_url['scheme'] . '://' . $parsed_url['host']);
									}
								}
							}
						}
					}
				}
			}
			$policy = $response->getContentSecurityPolicy();

			$domains = array_unique($domains);

			foreach ($domains as $domain) {
				$policy->addAllowedImageDomain($domain);
			}

			$policy->disallowImageDomain("'self'");
			$response->setContentSecurityPolicy($policy);
		}
*/
		// TODO: Make this editable via the UI
		// Remove unused categories
		// Add a "Protected" category to the app list for the instance admin
		if (
			$methodName == 'listCategories' &&
			$this->isInstanceAdmin
		) {
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

			$allApps = [[
				'id'			=> 'all',
				'ident'			=> 'all',
				'displayName'	=> 'All Apps',
			]];

			$data = array_merge($allApps, $data);

			if ($this->isInstanceAdmin) {
				$protected = [
					'id'			=> 'protected',
					'ident'			=> 'protected',
					'displayName'	=> 'Protected',
				];

				array_push($data, $protected);
			}

			$response->setData($data);
		}

		// Only list apps that are installed; force current version (prevent upgrades)
		// Add all apps to the "All Apps" category
		// When loading the app manager, sort or hide all apps that are of a protected type (cannot be limited to specific groups)
		if ($methodName == 'listApps') {
			// TODO: Make this editable via the UI
			$hiddenApps = [
				'drop_account',
			];

			$data = $response->getData();
			$installedApps = array_column($ocApp->listAllApps(), 'id');
			$installedApps = array_flip($installedApps);

			foreach ($data['apps'] as $key=>$app) {
				if (
					isset($installedApps[$app['id']]) ||
					array_key_exists($app['id'], $installedApps)
				) {
					$data['apps'][$key]['version'] = OC::$server->getAppManager()->getAppVersion($app['id']);

					if (!(
						isset($data['apps'][$key]['category']) ||
						array_key_exists('category', $data['apps'][$key])
					)) {
						$data['apps'][$key]['category'] = array('all');
					}
					else if (is_array($data['apps'][$key]['category'])) {
						array_push($data['apps'][$key]['category'], 'all');
					}
					else {
						$data['apps'][$key]['category'] = array(
							$data['apps'][$key]['category'],
							'all',
						);
					}
				}
				else {
					unset($data['apps'][$key]);
					continue;
				}

				if (OC::$server->getAppManager()->hasProtectedAppType($data['apps'][$key]['types'])) {
					// If the user is not the instance admin, hide all protected apps
					if (! $this->isInstanceAdmin) {
						unset($data['apps'][$key]);
						continue;
					}

					// If the user IS the instance admin, add protected apps to the "protected" category
					else {
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
				if (in_array($data['apps'][$key]['id'], $hiddenApps) && ! $this->isInstanceAdmin) {
					unset($data['apps'][$key]);
				}
			}

			$data['apps'] = array_values($data['apps']);
			$response->setData($data);
		}

		return $response;
	}

	// Haven't handled the case where instance admin enables app for all users without limiting to groups and then a regular user disables the app:
	// - Hide the app from all other users and make the app mandatory?
	// - Automatically enable app for all existing groups?
	//
	// Assumes all apps are already downloaded by the instance admin and on the system
	private function toggleApp($enable) {
		$params = json_decode(file_get_contents('php://input'),1);

		if (sizeof($params['appIds']) == 1) {// && ! isset($params['groups'])) {
			$app = $params['appIds'][0];
			$appTypes = OC_App::getAppInfo($app)['types'];

			if (! OC::$server->getAppManager()->hasProtectedAppType($appTypes)) {

				$userGroups = OC::$server->getGroupManager()->getUserIdGroups($this->userUID);

				// If the user is in a self-named group
				if ((isset($userGroups['admin-' . $this->userUID]) || array_key_exists('admin-' . $this->userUID, $userGroups))) {
					$userGroup = $userGroups['admin-' . $this->userUID];
					$userGID = $this->userUID;
				}
				// This should not happen - user is not an admin and shouldn't be able to modify apps
				else {
					die;
				}

				$enabled = \OC::$server->getConfig()->getAppValue($app, 'enabled', 'no');
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

				$groups = array();

				if (is_array($groupIds)) {// && $active) {
					foreach ($groupIds as $groupId) {
						if ($groupId != $userGID) {
							array_push($groups, OC::$server->getGroupManager()->get('admin-' . $groupId));
						}
					}
					if ($enable) {
						array_push($groups, OC::$server->getGroupManager()->get('admin-' . $userGID));
					}
				}

				// Need a way to list all groups on the server and remove user from that list
				else if (! is_array($groups) && $active) {
					$groups = array();
				}

				// These should literally not be possible - call was made to disable an app, but $active = false?
				else if (! is_array($groups) && ! $active) {
				}
				else if (! is_array($groups) && ! $active) {
				}

				// Let the controller go ahead and disable the app for all users
				if (sizeof($groups) == 0 && ! $enable) {
					return;
				}
				OC::$server->getAppManager()->enableAppForGroups($app, $groups);

				// There is likely a MUCH cleaner way to do this, but for now, it works
				if ($enable) {
					echo '{"data":{"update_required":false}}';
				}
				else {
					echo '[]';
				}

				OC::$server->getTempManager()->clean();
				OC::$server->getLockingProvider()->releaseAll();
				OC::$server->getSession()->__destruct();
				die;
			}
			// Throw an error - protected app (heck, the app should be hidden . . . )
			else {

			}
		}
	}
}
