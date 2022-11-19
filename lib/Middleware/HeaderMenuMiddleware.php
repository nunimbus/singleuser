<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OCP\AppFramework\Http\Response;
use OC\Accounts\AccountManager;
use OCP\IRequest;
use OC_App;
use OC;

class HeaderMenuMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		// Not instance admin and logged in
		if (OC::$server->getUserSession()->isLoggedIn() && ! $this->isInstanceAdmin) {

			// This SHOULD only match HTML. SHOULD.
			if ($output != strip_tags($output)) {

				// TODO: Make this editable via the UI
				// Remove the "About" listing from the menu
				$newOutput = preg_replace('/<li data-id="core_users">.*<li data-id/ms', '<li data-id', $output);

				if ($newOutput == $output) {
					OC::$server->getLogger()->warning(__FILE__ . ':' . __LINE__ . ' Failed to remove the About listing from the menu');
				}

				$output = $newOutput;

				// Remove the "Users" listing from the menu
				$newOutput = preg_replace('/<li data-id="firstrunwizard-about">.*<li data-id/ms', '<li data-id', $output);

				if ($newOutput == $output) {
					OC::$server->getLogger()->warning(__FILE__ . ':' . __LINE__ . ' Failed to remove the Users listing from the menu');
				}

				$output = $newOutput;

				return $output;
			}
		}
		return $output;
	}
}