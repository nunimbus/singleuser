<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC;

class HeaderMenuMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		if ($methodName == 'index') {
			// Not instance admin and logged in
			if (OC::$server->getUserSession()->isLoggedIn() && ! $this->isInstanceAdmin) {

				// This SHOULD only match HTML. SHOULD.
				if ($output != strip_tags($output)) {
        	        // TODO: Make this editable via the UI
					$sections = [
                        'admin_settings',
						'firstrunwizard_about',
						'core_users',
					];

					$sections = implode('|', $sections);
					$newOutput = $output;

					$newOutput = preg_replace("/<li data-id=\"($sections)\">\n.*\n.*\n.*\n.*\n.*<\/li>\n/mU", '', $output);
					return $newOutput;
				}
			}
		}
		return $output;
	}
}