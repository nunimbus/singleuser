<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Settings\Controller\PersonalSettingsController;
use OCP\AppFramework\Http\Response;
use OC;

class PersonalSettingsControllerMiddleware extends MiddlewareConstructor {
	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof PersonalSettingsController) {
			return $response;
		}

		$server = OC::$server;

		// Removes several sections that are not currently necessary or useful
		if (
			$server->getUserSession()->isLoggedIn() &&
			! $this->isAdmin
		) {
			// Should probably be moved to ControllerPermissionsMiddleware
			$blockedSections = array(
//				'sharing',
				'workflow',
				//'privacy',
			);

			foreach ($blockedSections as $section) {
				if ($server->getRequest()->getRequestUri() == "/index.php/settings/user/$section") {
					header("Location: /index.php/settings/user", 302);
					exit();
				}
			}

			$params = $response->getParams();

			foreach ($params['forms']['personal'] as $key=>$form) {
				if (in_array($form['anchor'], $blockedSections)) {
					unset($params['forms']['personal'][$key]);
				}
			}

			if (
				$server->getRequest()->getParams()['_route'] == 'settings.PersonalSettings.index' &&
				$server->getRequest()->getParams()['section'] == 'personal-info'
			) {
				$content = array_map('trim', array_filter(explode("\n", $params['content'])));

				// Remove sections from the "Personal Info" page
				$sections = array(
					'<div id="vue-role-section"></div>',
					'<div id="vue-headline-section"></div>',
					'<div id="vue-biography-section"></div>',
					'<div id="vue-profile-section"></div>',
					'<div id="vue-profile-visibility-section"></div>',
				);

				foreach ($sections as $section) {
					$index = array_search($section, $content);

					if ($index) {
						unset($content[$index]);
						unset($content[$index - 1]);
						unset($content[$index + 1]);
					}
				}

				// Hide the "Reasons to use Nextcloud in your organization" section
				$index = array_search('<div class="section development-notice">', $content);

				if ($index) {
					$content[$index] = '<div class="section development-notice hidden">';
				}

				$params['content'] = implode("\n", $content);
			}

			if (
				$server->getRequest()->getParams()['_route'] == 'settings.PersonalSettings.index' &&
				$server->getRequest()->getParams()['section'] == 'privacy'
			) {
				$content = array_map('trim', array_filter(explode("\n", $params['content'])));

				$index = array_search('<h4>Administrators</h4>', $content);
				if ($index) {
					unset($content[$index]);
					unset($content[$index + 1]);
				}

				$index = array_search('<h3>Where is your data?</h3>', $content);
				if ($index) {
					unset($content[$index]);
					unset($content[$index - 1]);
					unset($content[$index + 1]);
					unset($content[$index + 2]);
				}
				
				$params['content'] = implode("\n", $content);
			}

			$response->setParams($params);
		}

		return $response;
	}

	public function beforeOutput($controller, $methodName, $output){
		$server = OC::$server;

		if (!
			($controller instanceof PersonalSettingsController &&
			$server->getRequest()->getParams()['_route'] == 'settings.PersonalSettings.index' &&
			$server->getRequest()->getParams()['section'] == 'personal-info')
		) {
			return $output;
		}

		// Not admin and logged in
		if (
			$server->getUserSession()->isLoggedIn() &&
			! $this->isAdmin
		) {
			// This SHOULD only match HTML. SHOULD.
			if ($output != strip_tags($output)) {
				$version = $server->getConfig()->getSystemValue('version');
				$mainVersion = explode('.', $version)[0];

				// Remove "You are a member of the following groups" from "Personal Info"
				if ($mainVersion < 25) {
					$output = preg_replace('#<div id="groups".*<div id="quota"#ms', '<div id="quota"', $output);
				}
				else {
					$output = array_map('trim', array_values(array_filter(explode("\n", $output))));

					// All of the `#initial-state-settings` elements should follow the </noscript> element
					$i = array_search('</noscript>', $output);

					if ($i) {
						$styles = '
						<style>
							.details__groups {
								display:none !important
							}
							.federation-actions,
							.federation-actions--additional {
								display:none !important
							}
						</style>';

						$output[$i] = $output[$i] . $styles;

						for ($i; $i < sizeof($output); $i++) {
							if (str_starts_with($output[$i], '<input type="hidden" id="initial-state-settings-personalInfoParameters"')) {
								$match = $output[$i];
								$matchParts = explode('value="', $match);
								$value = substr($matchParts[1], 0, -2);
								$valueJson = base64_decode($value);
								$valueArr = json_decode($valueJson, true);

								// Unset the 'scope' elements to hide the permissions buttons
								foreach ($valueArr as $key=>$el) {
									if (
										is_array($el) &&
										(isset($el['scope']) || array_key_exists('scope', $el))
									 ) {
										unset($valueArr[$key]['scope']);
									}
								}

								unset($valueArr['emailMap']['primaryEmail']['scope']);

								// Removing the 'scope' elements causes JS errors on 'additionalEmails,' so permissions
								// buttons cannot be hidden this way. Hide the elements with CSS and block any calls to
								// the method in ControllerPermissionsMiddleware
								//foreach ($valueArr['emailMap']['additionalEmails'] as $emailKey=>$email) {
								//	$valueArr['emailMap']['additionalEmails'][$emailKey]['scope'] = "";
								//}

								// Can't delete the 'groups' element - it throws JS errors. So, set it to empty and hide with CSS
								$valueArr['groups'] = array();

								// Depends on `profileEnabledGlobally` being set to `true` in next block
								unset($valueArr['role']);
								unset($valueArr['headline']);
								unset($valueArr['biography']);

								$valueJson = json_encode($valueArr);
								$valueNew = base64_encode($valueJson);
								$matchParts[1] = $valueNew . '">';
								$output[$i] = implode('value="', $matchParts);
							}
							else if ($output[$i] == '<input type="hidden" id="initial-state-settings-profileEnabledGlobally" value="' . base64_encode('true') . '">') {
								$output[$i] = '<input type="hidden" id="initial-state-settings-profileEnabledGlobally" value="' . base64_encode('false') . '">';
							}
							else if (str_starts_with($output[$i], '<input type="hidden" id="initial-state-settings-profileParameters"')) {
								unset($output[$i]);
							}
						}
					}

					$output = implode("\n", $output);
				}

				return $output;
			}
		}
		return $output;
	}
}