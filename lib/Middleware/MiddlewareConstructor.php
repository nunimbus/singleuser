<?php

namespace OCA\SingleUser\Middleware;

use OCP\AppFramework\Middleware;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\IRequest;
use OC;

class MiddlewareConstructor extends Middleware {
	public $isAdmin = false;
	public $userUID = null;

	/** @var ControllerMethodReflector */
	public $reflector;

	public function __construct(IRequest $request,
								ControllerMethodReflector $reflector) {

		$this->reflector = $reflector;

		if (OC::$server->getUserSession()->isLoggedIn()) {
			$this->isAdmin = OC::$server->getGroupManager()->isAdmin(OC::$server->getUserSession()->getUser()->getUID());
			$this->userUID = OC::$server->getUserSession()->getUser()->getUID();
		}
	}
}