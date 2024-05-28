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

 namespace OCA\Files_Staticmimecontrol;

use OCA\Files_Staticmimecontrol\Activity\Provider;
use OCA\Files_Staticmimecontrol\AppInfo\Application;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Activity\IManager as ActivityManager;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class Item {
	/**
	 * file handle, user to read from the file
	 *
	 * @var resource
	 */
	protected $fileHandle;

	private AppConfig $config;
	private ActivityManager $activityManager;
	private LoggerInterface $logger;
	private IRootFolder $rootFolder;
	private IAppManager $appManager;
	private File $file;
	private bool $isCron;

	/**
	 * Item constructor.
	 */
	public function __construct(
		AppConfig $appConfig,
		ActivityManager $activityManager,
		LoggerInterface $logger,
		IRootFolder $rootFolder,
		IAppManager $appManager,
		File $file,
		bool $isCron
	) {
		$this->config = $appConfig;
		$this->activityManager = $activityManager;
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->file = $file;
		$this->isCron = $isCron;
	}

	/**
	 * Reads a file portion by portion until the very end
	 *
	 * @return string|false
	 */
	public function fread() {
		if (!($this->file->getSize() > 0)) {
			return false;
		}

		if (is_null($this->fileHandle)) {
			$this->getFileHandle();
		}

		if (!is_null($this->fileHandle) && !$this->feof()) {
			return fread($this->fileHandle, $this->config->getAvChunkSize());
		}
		return false;
	}

	/**
	 * 	 * Action to take if this item is infected
	 */
	public function processInfected(Status $status): void {
		$infectedAction = $this->config->getAvInfectedAction();

		$shouldDelete = $infectedAction === 'delete';

		$message = $shouldDelete ? Provider::MESSAGE_FILE_DELETED : '';

		$userFolder = $this->rootFolder->getUserFolder($this->file->getOwner()->getUID());
		$path = $userFolder->getRelativePath($this->file->getPath());

		$activity = $this->activityManager->generateEvent();
		$activity->setApp(Application::APP_NAME)
			->setSubject(Provider::SUBJECT_ILLEGALMIMETYPE_DETECTED, [$status->getDetails()])
			->setMessage($message)
			->setObject('file', $this->file->getId(), $path)
			->setAffectedUser($this->file->getOwner()->getUID())
			->setType(Provider::TYPE_ILLEGALMIMETYPE_DETECTED);
		$this->activityManager->publish($activity);

		if ($shouldDelete) {
			if ($this->isCron) {
				$msg = 'Infected file deleted (during background scan)';
			} else {
				$msg = 'Infected file deleted.';
			}
			$this->logError($msg . ' ' . $status->getDetails());
			$this->deleteFile();
		} else {
			if ($this->isCron) {
				$msg = 'Infected file found (during background scan)';
			} else {
				$msg = 'Infected file found.';
			}
			$this->logError($msg . ' ' . $status->getDetails());
		}
	}

	/**
	 * 	 * Action to take if this item status is unclear
	 * 	 *
	 *
	 * @param Status $status
	 */
	public function processUnchecked(Status $status): void {
		//TODO: Show warning to the user: The file can not be checked
		$this->logError('Not Checked. ' . $status->getDetails());
	}

	/**
	 * Check if the end of file is reached
	 */
	private function feof(): bool {
		$isDone = feof($this->fileHandle);
		if ($isDone) {
			$this->logDebug('Scan is done');
			$handle = $this->fileHandle;
			fclose($handle);
			$this->fileHandle = null;
		}
		return $isDone;
	}

	/**
	 * 	 * Opens a file for reading
	 * 	 *
	 *
	 * @throws \RuntimeException
	 */
	private function getFileHandle(): void {
		$fileHandle = $this->file->fopen('r');
		if ($fileHandle === false) {
			$this->logError('Can not open for reading.');
			throw new \RuntimeException();
		}

		$this->logDebug('Scan started');
		$this->fileHandle = $fileHandle;
	}

	/**
	 * 	 * Delete infected file
	 */
	private function deleteFile(): void {
		//prevent from going to trashbin
		if ($this->appManager->isEnabledForUser('files_trashbin')) {
			/** @var ITrashManager $trashManager */
			$trashManager = \OC::$server->get(ITrashManager::class);
			$trashManager->pauseTrash();
		}
		$this->file->delete();
		if ($this->appManager->isEnabledForUser('files_trashbin')) {
			/** @var ITrashManager $trashManager */
			$trashManager = \OC::$server->get(ITrashManager::class);
			$trashManager->resumeTrash();
		}
	}

	private function generateExtraInfo(): string {
		$owner = $this->file->getOwner();

		if ($owner === null) {
			$ownerInfo = ' Account: NO OWNER FOUND';
		} else {
			$ownerInfo = ' Account: ' . $owner->getUID();
		}

		$extra = ' File: ' . $this->file->getId()
			. $ownerInfo
			. ' Path: ' . $this->file->getPath();

		return $extra;
	}

	/**
	 * @param string $message
	 */
	public function logDebug($message): void {
		$this->logger->debug($message . $this->generateExtraInfo(), ['app' => 'files_antivirus']);
	}

	/**
	 * @param string $message
	 */
	public function logError($message): void {
		$this->logger->error($message . $this->generateExtraInfo(), ['app' => 'files_antivirus']);
	}
}
