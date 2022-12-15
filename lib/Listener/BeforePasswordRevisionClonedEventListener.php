<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Andrew Summers
 * *
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

use OCA\Passwords\Events\PasswordRevision\BeforePasswordRevisionClonedEvent;
use OCA\Passwords\Exception\ApiException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class BeforePasswordRevisionClonedEventListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof BeforePasswordRevisionClonedEvent)) {
			return;
		}

		if (\OC::$server->getRequest()->getPathInfo() == '/apps/passwords/api/1.0/share/create') {
			$data = file_get_contents('php://input');
			$json = json_decode($data, true);
			$search = $json['receiver'];

			$result = null;
			$result = \OC::$server->getUserManager()->getByEmail($search);

			if (sizeof($result) == 0) {
				\OC::$server->getUserManager()->userExists($search);
			}

			if (sizeof($result) == 0) {
				$event->stopPropagation();
				throw new ApiException('Provided username or email must be an exact match.', 404);
				exit();
			}
		}
	}
}
