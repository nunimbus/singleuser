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
 
 use OCA\FederatedFileSharing\FederatedShareProvider;
 
 class ShareListener {
	 public static function preShare($params) {
		$share = $params->getSubject();
		$share->setSharedWith($share->getSharedWith() . '@nextcloud.nunimbus.com');
		$share->setSharedBy($share->getSharedBy() . '@nextcloud.nunimbus.com');
		$share->setShareType(6);
		$share->setShareOwner(\OC::$server->getUserSession()->getUser()->getUID());
	}

	public static function postShare($params) {
		$share = $params->getSubject();
		$shareId = $share->getId();

		\OC::$server->get(FederatedShareProvider::class)->storeRemoteId((int) $shareId, $shareId);
	}

	public static function preUnshare($params) {
	}

	public static function postUnshare($params) {
	}

	public static function postAcceptShare($params) {
	}
}