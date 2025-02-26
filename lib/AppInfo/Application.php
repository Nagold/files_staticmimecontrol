<?php

/**
 * @copyright Copyright (c) 2022 Alexander Volz <gh-contact@volzit.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FilesStaticmimecontrol\AppInfo;

use OC\Files\Filesystem;
use OCA\Files_Sharing\SharedStorage;
use OCA\FilesStaticmimecontrol\StorageWrapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\Util;

class Application extends App implements IBootstrap {
	public function __construct() {
		parent::__construct('files_staticmimecontrol');
	}

	/**
	 * Adds storage wrapper.
	 *
	 * @internal
	 */
	public function addStorageWrapper(): void {
		Filesystem::addStorageWrapper('files_staticmimecontrol', [$this, 'addStorageWrapperCallback'], -10);
	}

	/**
	 * Callback for adding storage wrapper.
	 *
	 * @internal
	 * @param IStorage $storage
	 * @return StorageWrapper|IStorage
	 */
	public function addStorageWrapperCallback(string $mountPoint, IStorage $storage): IStorage {
		if (php_sapi_name() !== 'cli' && !$storage->instanceOfStorage(SharedStorage::class)) {
			$config = $this->getContainer()->get(IConfig::class);

			return new StorageWrapper([
			], storage:$storage, config:$config);
		}
		return $storage;
	}


	public function register(IRegistrationContext $context): void {
		//currently no replacement event available!
		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
	}

	public function boot(IBootContext $context): void {
		// No initialization needed
	}
}
