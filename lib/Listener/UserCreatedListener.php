<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Andrew Summers
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\SingleUser\Listener;

use OCP\User\Events\UserCreatedEvent;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OC\Accounts\AccountManager;
use OCA\Provisioning_API\Controller\UsersController;
use \PDO;

use OC;

class UserCreatedListener implements IEventListener {
	/**
	 * @var IUserSession
	 */
	private $userSession;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IJobList
	 */
	private $jobList;

	public function __construct(
		IConfig $config,
		IUserSession $userSession,
		IJobList $jobList
	) {
		$this->userSession = $userSession;
		$this->config = $config;
		$this->jobList = $jobList;
	}

	public function handle(Event $event): void {
		if (!$event instanceof \OCP\User\Events\UserCreatedEvent) {
				return;
		}

		// Make the user an admin
		$user = $event->getUser();
		$userUID = $user->getUID();

		$userGroup = OC::$server->getGroupManager()->createGroup('admin-' . $userUID);
		$adminGroup = OC::$server->getGroupManager()->get('admin');

		$userGroup->addUser($user);
		$adminGroup->addUser($user);

		// Make the user the admin of the self-named group
		$userController = \OC::$server->query(UsersController::class);
		$userController->addSubAdmin($userUID, 'admin-' . $userUID);

		// TODO: Make this editable via the UI
		// Set the storage quota
		$user->setQuota('20GB');

		// TODO: Make this editable via the UI
		// Add a list of apps that should be enabled by default
		//\OC::$server->getAppManager()->enableApp('encryption');

		return;
	}
}