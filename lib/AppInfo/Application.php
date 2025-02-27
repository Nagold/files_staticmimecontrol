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
use OCA\FilesStaticmimecontrol\Listener\BeforeFileSystemSetupListener;
use OCA\FilesStaticmimecontrol\StorageWrapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\BeforeFileSystemSetupEvent;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\IUserSession;

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
			$userSession = $this->getContainer()->get(IUserSession::class);

			return new StorageWrapper([
			], storage:$storage, config:$config, userSession:$userSession);
		}
		return $storage;
	}


	public function register(IRegistrationContext $context): void {
		//nothing to do here
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IEventDispatcher $eventDispatcher) {
			$eventDispatcher->addListener(
				BeforeFileSystemSetupEvent::class,
				[new BeforeFileSystemSetupListener($this), 'handle']
			);
		});
	}
}
