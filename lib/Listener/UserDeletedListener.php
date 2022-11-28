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

use OCP\User\Events\UserDeletedEvent;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;

use OC;

class UserDeletedListener implements IEventListener {
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
		if (!$event instanceof \OCP\User\Events\UserDeletedEvent) {
				return;
		}

		$user = $event->getUser();
		$userUID = $user->getUID();

		OC::$server->getGroupManager()->get('admin-' . $userUID)->delete();

		return;
	}
}
