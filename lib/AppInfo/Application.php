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

namespace OCA\Files_Staticmimecontrol\AppInfo;

use OC;
use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Jail;
#use OCA\Files_Sharing\SharedStorage;
#use OCA\Files_Staticmimecontrol\StorageWrapper;
use OCA\Files_Staticmimecontrol\MimetypeWrapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Activity\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\App\IAppManager;
use OCP\Files\IHomeStorage;
use OCP\Files\Storage\IStorage;
use OCP\IL10N;
use OCP\Util;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public const APP_NAME = 'files_staticmimecontrol';

	public function __construct() {
		parent::__construct(self::APP_NAME);
	}

#	/**
#	 * @internal
#	 */
#	public function addStorageWrapper() {
#		// Needs to be added as the first layer
#		Filesystem::addStorageWrapper('files_staticmimecontrol', [$this, 'addStorageWrapperCallback'], -10);
#	}
#
#	/**
#	 * @internal
#	 * @param $mountPoint
#	 * @param IStorage $storage
#	 * @return StorageWrapper|IStorage
#	 */
#	public function addStorageWrapperCallback($mountPoint, IStorage $storage) {
#		if (!OC::$CLI && !$storage->instanceOfStorage(SharedStorage::class)) {
#			return new StorageWrapper([
#				'storage' => $storage,
#				'mountPoint' => $mountPoint,
#				'userSession' => \OC::$server->getUserSession(),
#			]);
#		}
#
#		return $storage;
#	}

	public function setupWrapper(): void {
		Filesystem::addStorageWrapper(
			'oc_staticmimetypecontrol',
			function (string $mountPoint, IStorage $storage) {
				if ($storage->instanceOfStorage(Jail::class)) {
					// No reason to wrap jails again
					return $storage;
				}

				$container = $this->getContainer();
				$l10n = $container->get(IL10N::class);
				$logger = $container->get(LoggerInterface::class);
				$activityManager = $container->get(IManager::class);
				$eventDispatcher = $container->get(IEventDispatcher::class);
				$appManager = $container->get(IAppManager::class);
				return new MimetypeWrapper([
					'storage' => $storage,
					'l10n' => $l10n,
					'logger' => $logger,
					'activityManager' => $activityManager,
					'isHomeStorage' => $storage->instanceOfStorage(IHomeStorage::class),
					'eventDispatcher' => $eventDispatcher,
					'trashEnabled' => $appManager->isEnabledForUser('files_trashbin'),
				]);
			},
			1
		);
	}

	public function register(IRegistrationContext $context): void {
#		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'setupWrapper');
	}

	public function boot(IBootContext $context): void {
	}
}
