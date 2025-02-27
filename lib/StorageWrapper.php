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

namespace OCA\FilesStaticmimecontrol;

use Exception;
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\ForbiddenException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\IConfig;
use OCP\IUserSession;
use function OCP\Log\logger;

class StorageWrapper extends Wrapper implements IWriteStreamStorage {
	private IConfig $config;

	protected $storage;
	private IUserSession $userSession;


	public function __construct(array $parameters, IStorage $storage, IConfig $config, IUserSession $userSession) {
		parent::__construct(['storage' => $storage]);
		$this->config = $config;
		$this->storage = $storage;
		$this->userSession = $userSession;
	}

	/**
	 * Reads the JSON config rules.
	 *
	 * @param string $path
	 * @param string $mimetype
	 * @return array
	 */
	public function readRules(string $path, string $mimetype): array {
		$config = $this->readConfig();

		if (!isset($config['rules']) || !is_array($config['rules'])) {
			return [];
		}

		return array_filter($config['rules'], function ($rule) use ($path, $mimetype) {
			return preg_match('%' . $rule['path'] . '%', $path) &&
				preg_match('%' . $rule['mime'] . '%', $mimetype);
		});
	}

	/**
	 * Reads the JSON config.
	 *
	 * @return array
	 */
	public function readConfig(): array {
		try {
			$datadir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
			$jsonFile = $this->config->getSystemValue('staticmimecontrol_file', $datadir . '/staticmimecontrol.json');

			logger('files_staticmimecontrol')->debug('Reading staticmimecontrol config file: ' . $jsonFile);

			if (!is_file($jsonFile)) {
				logger('files_staticmimecontrol')->error('Config file not found: ' . $jsonFile);
				return [];
			}

			$configData = json_decode(file_get_contents($jsonFile), true);

			if (empty($configData)) {
				logger('files_staticmimecontrol')->error('Config file is empty or contains invalid JSON: ' . $jsonFile);
				return [];
			}

			return $configData ?? [];
		} catch (Exception $e) {
			logger('files_staticmimecontrol')->error('Error reading files_staticmimecontrol config: ' . $e->getMessage());
			return [];
		}
	}


	/**
	 * Checks if access to a file is allowed.
	 *
	 * @throws ForbiddenException
	 */
	protected function checkFileAccess(string $path, bool $isDir = false): void {
		$config = $this->readConfig();
		$denyRoot = $config['denyrootbydefault'] ?? true;

		$absolutePath = $this->storage->instanceOfStorage(Jail::class)
			? $this->storage->getUnjailedPath($path)
			: $path;


		if ($absolutePath === 'files' && $denyRoot) {
			logger('files_staticmimecontrol')->warning('Users [' . $this->getCurrentUser() . '] Access was denied to default folder');
			throw new ForbiddenException('Access denied to default folder', false);
		}



		$newPath = ltrim(str_replace('files', '', $absolutePath), '/');

		if (!str_starts_with($newPath, 'appdata_oc') && !str_starts_with($newPath, 'uploads/')) {
			if ($parentPath = dirname($newPath)) {
				$mime = $this->storage->getMimeType($path) ?? '';

				if ($mime === 'httpd/unix-directory') {
					return;
				}

				if (empty($this->readRules($parentPath, $mime))) {

					if ($mime != '') {
						logger('files_staticmimecontrol')->warning('Users [' . $this->getCurrentUser() . '] Access was denied to ' . $newPath . ' (absolute Path: ' . $absolutePath . ') with mime type: ' . $mime);
					}
					throw new ForbiddenException("Access denied to $mime in folder $newPath (absolute Path: $absolutePath )", false);
				}
			}
		}
	}

	private function getCurrentUser(): ?string {
		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : 'unknown User';
	}

	public function isCreatable(string $path): bool {
		try {
			$this->checkFileAccess($path);
			return $this->storage->isCreatable($path);
		} catch (ForbiddenException) {
			return false;
		}
	}

	public function isUpdatable(string $path): bool {
		try {
			$this->checkFileAccess($path);
			return $this->storage->isUpdatable($path);
		} catch (ForbiddenException) {
			return false;
		}
	}

	public function file_put_contents(string $path, mixed $data): int|float|false {
		$this->checkFileAccess($path);
		return $this->storage->file_put_contents($path, $data);
	}

	public function touch(string $path, ?int $mtime = null): bool {
		$this->checkFileAccess($path);
		return $this->storage->touch($path, $mtime);
	}

	public function writeStream(string $path, $stream, ?int $size = null): int {
		$result = $this->storage->writeStream($path, $stream, $size);
		try {
			$this->checkFileAccess($path);
		} catch (ForbiddenException $e) {
			$this->storage->unlink($path);
			throw $e;
		}
		return $result;
	}
}
