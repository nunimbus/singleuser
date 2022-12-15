<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC;

class DomManipulationMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		// Not instance admin and logged in
		if (OC::$server->getUserSession()->isLoggedIn() && ! $this->isInstanceAdmin) {

		}
		else {
			// This SHOULD only match HTML. SHOULD.
			if ($output != strip_tags($output)) {
				if ($controller instanceof \OC\Core\Controller\ClientFlowLoginController) {
					$output = preg_replace('#<span class="warning">#', '<span class="warning hidden">', $output);
					$icon = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/core/img/actions/info-white.svg');
					$authTokenHelp = '
					<style>
						input[type="checkbox"] {
							display: none;
						}
						.wrap-collabsible {
							margin: auto;
							padding-top: 10px;
						}
						.lbl-toggle {
							display: block;
							font-weight: 700;
							font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
							font-size: 14px;
							text-align: center;
							color: #DDD;
							cursor: pointer;
							transition: all 0.25s ease-out;
							width: 260px;
							margin: auto;
						}
						.lbl-toggle:hover {
							color: #FFF;
						}
						.lbl-toggle::before {
							display: inline-block;
							border-top: 5px solid transparent;
							border-bottom: 5px solid transparent;
							border-left: 5px solid currentColor;
							vertical-align: middle;
							margin-right: .7rem;
						}
						.collapsible-content {
							max-height: 0px;
							overflow: hidden;
							transition: max-height .25s ease-in-out;
							text-align: justify;
						}
						.toggle:checked+.lbl-toggle+.collapsible-content {
							max-height: 350px;
						}
						.toggle:checked+.lbl-toggle {
							border-bottom-right-radius: 0;
							border-bottom-left-radius: 0;
						}
						.collapsible-content .content-inner {
							/*background: #0082c9;
							border: 1px solid white;
							border-radius: 7px;*/
							padding: 0px 40px;
						}
						.content-inner ol {
							margin-left: 15px;
						}
						.collapsible-content p {
							margin-bottom: 0;
						}
						.info-icon {
							display: inline-block;
						}
						.info-icon svg {
							height: 12px;
							margin-bottom: -1px;
						}
						.info-icon svg path {
							fill: #ddd;
							transition: all 0.25s ease-out;
						}
						.lbl-toggle:hover .info-icon svg path {
							fill: #fff;
						}
					</style>
					<div class="wrap-collabsible">
						<input id="collapsible" class="toggle" type="checkbox">
						<label for="collapsible" class="lbl-toggle">
							<div class="info-icon">' . $icon . '</div>
							<!--img src="/core/img/actions/info-white.svg" class="info-icon"</-->
							What\'s This?
						</label>
						<div class="collapsible-content">
						<div class="content-inner">
							<p>
								With two-factor authentication enabled, some apps are unable log in with the additional
								six-digit passcode. An app token is a randomly-generated single-use password allowing
								you to log in with just your username and the token.
								<br><br>
								To create a new token:
								<ol>
									<li>Log into your Nextcloud account using a browser.</li>
									<li>Click the avatar button in the top right and select settings.</li>
									<li>Navigate to the "Security" section</li>
									<li>At the bottom of the "Devices & sessions" section, provide an app name (for your
									reference) and click "Create new app password."</li>
									<li>Use the characters provided as a password to log into the app with your username.</li>
								</ol>
							</p>
						</div>
						</div>
					</div>

					';
					$output = preg_replace('#(</form>)#', "$authTokenHelp$1", $output);
				}

				$brokenApps = [
					'Nextcloud Passwords App',
				];

				if ($controller instanceof \OC\Core\Controller\ClientFlowLoginController && in_array(OC::$server->getRequest()->getHeader('User-Agent'), $brokenApps, true)) {
					$output = preg_replace('#<p id="redirect-link">#', '<p id="redirect-link" class="hidden">', $output);
					$output = preg_replace('#(<form.*)class="hidden"#', '$1class=""', $output);
					$output = preg_replace('#<a id="app-token-login" class="warning"#', '<a id="app-token-login" class="warning hidden"', $output);

					$info = 'Standard username and password login is broken for this app due to the two-factor system
							used by NuNimbus. We are working with the app developer to fix this issue. Until then,
							please log in using an app token, instead.';

					// Remove the scary and confusing warnings from the mobile login page
					$output = preg_replace('#<p class="info">.*<span class="warning hidden">#ms', '<p class="info">' . $info . '</p><span class="warning hidden">', $output);
				}
			}
		}

		return $output;
	}
}
