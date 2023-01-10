<?php

namespace OCA\SingleUser\Middleware;

use OCA\SingleUser\Middleware\MiddlewareConstructor;
use OC;

class DomManipulationMiddleware extends MiddlewareConstructor {

	public function beforeOutput($controller, $methodName, $output){
		// This SHOULD only match HTML. SHOULD.
		if ($output != strip_tags($output)) {
			if ($controller instanceof \OC\Core\Controller\ClientFlowLoginController) {
				// Remove the scary and confusing warnings from the mobile login page
				$output = explode("\n", $output);

				foreach ($output as $key=>$line) {
					$trLine = trim($line);

					if ($trLine == '<div class="notecard warning">') {
						$output[$key] = '<div class="notecard warning hidden">';
						break;
					}
				}

				$output = implode("\n", $output);
			}
		}

		return $output;
	}
}
