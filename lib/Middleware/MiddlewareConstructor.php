<?php

namespace OCA\SingleUser\Middleware;

use OCP\AppFramework\Middleware;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\IRequest;
use OC;

class MiddlewareConstructor extends Middleware {
	public $isInstanceAdmin = false;
	public $isAdmin = false;
	public $userUID = null;

	/** @var ControllerMethodReflector */
	public $reflector;

	public function __construct(IRequest $request,
								ControllerMethodReflector $reflector) {

		$this->reflector = $reflector;

		if (OC::$server->getUserSession()->isLoggedIn()) {
			$groupManager = OC::$server->getGroupManager();
			$user = OC::$server->getUserSession()->getUser();
			$userUID = $user->getUID();

			$this->userUID = $userUID;

			if ($groupManager->isInGroup($userUID, 'instance-admin')) {
				$this->isInstanceAdmin = true;
			}
			if ($groupManager->isInGroup($userUID, 'admin')) {
				$this->isAdmin = true;
			}
		}
	}
}