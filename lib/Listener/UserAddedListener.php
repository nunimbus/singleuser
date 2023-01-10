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

use OCP\Group\Events\UserAddedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OC;

// Use this event to pre-select defaults for new users. When using a SAML backend, UserCreatedEvent doesn't fire, but
// UserAddedEvent (added to group) does. Add code to enable/disable apps and configure settings here
class UserAddedListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof UserAddedEvent) {
				return;
		}

		$user = $event->getUser();
		$userId = $user->getUID();
		$groupId = $event->getGroup()->getGID();

		if ($groupId == 'user-' . $userId && $user->getLastLogin() == 0) {
			$appSettingsControllerMiddleware = OC::$server->get('OCA\SingleUser\Middleware\AppSettingsControllerMiddleware');
			$appSettingsControllerMiddleware->toggleApp(false, $userId, 'logreader');
			$appSettingsControllerMiddleware->toggleApp(false, $userId, 'serverinfo');
			$appSettingsControllerMiddleware->toggleApp(false, $userId, 'support');
			$appSettingsControllerMiddleware->toggleApp(false, $userId, 'survey_client');
			$appSettingsControllerMiddleware->toggleApp(false, $userId, 'updatenotification');
		}

		return;
	}
}
