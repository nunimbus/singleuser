<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCA\Settings\Controller\PersonalSettingsController;
use OCP\AppFramework\Http\Response;
use OC\Accounts\AccountManager;
use OCP\IRequest;
use OC_App;

class PersonalSettingsControllerMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		if (! $controller instanceof PersonalSettingsController) {
			return $output;
		}

		$server = \OC::$server;

		// Not instance admin and logged in
		if (
			$server->getUserSession()->isLoggedIn() && 
			! $this->isInstanceAdmin
		) {
			// This SHOULD only match HTML. SHOULD.
			if ($output != strip_tags($output)) {
				// TODO: Make this editable via the UI
				// Remove "You are a member of the following groups" from "Personal Info"
				$output = preg_replace('#<div id="groups".*<div id="quota"#ms', '<div id="quota"', $output);

				// Remove "Reasons to use Nextcloud in your organization" section
				$output = preg_replace('#<div class="section development-notice#', '<div class="section development-notice hidden', $output);

				// Adds a link to the Keycloak change password page (this should really be in a `user_saml` extension plugin)
/*				if ($server->getUserSession()->getUser()->getBackendClassName() == 'user_saml') {
					$idpUrl = $server->getAppConfig()->getValue('user_saml', 'idp-entityId');

					if ($idpUrl) {
						$ch = curl_init();
						curl_setopt($ch, CURLOPT_URL, $idpUrl);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

						$result = curl_exec($ch);
						if (curl_errno($ch)) {
							echo 'Error:' . curl_error($ch);
						}
						else {
							$json = json_decode($result, true);

							if (
								is_array($json) &&
								(isset($json['account-service']) || array_key_exists('account-service', $json))
							) {
								$path = explode('/', $server->getRequest()->getRequestUri());
			
								if (end($path) == 'security') {
									$passwordBlock = '
									<div id="security-password" class="section">
										<h2 class="inlineblock">Password</h2>
										<div class="personal-settings-setting-box personal-settings-password-box">
											<a target="_blank" href="' . $json['account-service'] . '/#/security/signingin">
												<input id="passwordbutton" type="submit" value="Change password">
											</a>
										</div>
									</div>';

									$output = preg_replace('#<div id="app-content">#', '<div id="app-content">' . $passwordBlock, $output);
								}
							}	
						}
						curl_close($ch);
					}
				}
*/				return $output;
			}
		}
		return $output;
	}

	public function afterController($controller, $methodName, Response $response): Response {
		if (! $controller instanceof PersonalSettingsController) {
			return $response;
		}

		// Remove the "Administration" section from the personal settings page of non-instance admins
		// Also removes the "Sharing" sectino (not currently necessary or useful)
		if (
			\OC::$server->getUserSession()->isLoggedIn() && 
			! $this->isInstanceAdmin
		) {
			$params = $response->getParams();
			$params['forms']['admin'] = array();

			if (\OC::$server->getRequest()->getRequestUri() == "/index.php/settings/user/sharing") {
				header("Location: /index.php/settings/user", 302);
				exit();
			}
			else {
				foreach ($params['forms']['personal'] as $key=>$form) {
					if ($form['anchor'] == 'sharing') {
						unset($params['forms']['personal'][$key]);
					}
				}
				unset($params['forms']['personal']['sharing']);
			}
			$response->setParams($params);
		}
		return $response;
	}
}
