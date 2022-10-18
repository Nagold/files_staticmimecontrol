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

use \Exception as Exception;
use OC\Files\Storage\Storage;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\ForbiddenException;
use OCP\Files\Storage\IWriteStreamStorage;

class StorageWrapper extends Wrapper implements IWriteStreamStorage {
	/** @var string */
	public $mountPoint;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->mountPoint = $parameters['mountPoint'];
	}

	/**
	 * reads the json config rules
	 *
	 * @param string $path
	 * @param string $mimetype
	 * @return array
	 */
	public function readRules($path, $mimetype) {
		$staticmimecontrolcfg = $this->readConfig();
		if (is_array($staticmimecontrolcfg) && array_key_exists("rules", $staticmimecontrolcfg)) {
			$staticmimecontrolrules = $staticmimecontrolcfg["rules"];
			$staticmimecontrolrulesfiltered = array_filter($staticmimecontrolrules, function ($value) use ($path, $mimetype) {

				$pathmatch = preg_match('%' . $value["path"] . '%' , $path);
				$mimematch = preg_match('%' . $value["mime"] . '%' , $mimetype);
				return ($pathmatch && $mimematch);
			});
			return $staticmimecontrolrulesfiltered;
		}
		return [];
	}



	/**
	 * reads the json config
	 *
	 * @return array
	 */
	public function readConfig() {
		try {
			$config = \OC::$server->getConfig();
			$datadir = $config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
			$jsonFile = $config->getSystemValue('staticmimecontrol_file', $datadir . '/staticmimecontrol.json');
		} catch (Exception $e) {
			error_log("error reading staticmimecontrol_file config: " . $e->getMessage(), 0);
			return [];
		}
		if (is_file($jsonFile)) {
			return json_decode(file_get_contents($jsonFile), true);
		}
		return [];
	}

	/**
	 * @throws ForbiddenException
	 */
	protected function checkFileAccess(string $path, bool $isDir = false): void {
		$prefix = "files";
		if (isset($this->readConfig()["denyrootbydefault"])) {
			$denyroot = $this->readConfig()["denyrootbydefault"];
		} else {
			$denyroot = true;
		}

		if ($path == $prefix && $denyroot) {
			throw new ForbiddenException('Access denied to default Folder', false);
		}

		$newpath = $path;
		if (substr($newpath, 0, strlen($prefix)) == $prefix) {
			$newpath = substr($newpath, strlen($prefix));
		}
		if (substr($newpath, 0, 1) == "/") {
			$newpath = substr($newpath, 1);
		}

		$prefix_1 = "appdata_oc";
		$prefix_2 = "uploads/";

		if (substr($newpath, 0, strlen($prefix_1)) != $prefix_1 && substr($path, 0, strlen($prefix_2)) != $prefix_2) {
			if (dirname($newpath) != "" && dirname($newpath) != ".") {

					$mime = $this->storage->getMimeType($path);
					if (!$mime || $mime == "httpd/unix-directory")
					{
						return;
					}
					$cfg = $this->readRules(dirname($newpath), $mime);



				if (count($cfg) === 0) {
					if (isset($mime)) {
						$msg = 'Access denied to '.$mime. ' in Folder '. $newpath;
					} else {
						$msg = 'Access denied in Folder '. $newpath;
					}

					error_log($msg, 0);
					throw new ForbiddenException($msg, false);
				}
			}
		}
	}



	/**
	 * check if a file can be created in $path
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isCreatable($path) {
		try {
			$this->checkFileAccess($path);
		} catch (ForbiddenException $e) {
			return false;
		}
		return $this->storage->isCreatable($path);
	}


	/**
	 * check if a file can be written to
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isUpdatable($path) {
		try {
			$this->checkFileAccess($path);
		} catch (ForbiddenException $e) {
			return false;
		}
		return $this->storage->isUpdatable($path);
	}


	/**
	 * see http://php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param string $data
	 * @return bool
	 * @throws ForbiddenException
	 */
	public function file_put_contents($path, $data) {
		$this->checkFileAccess($path);
		return $this->storage->file_put_contents($path, $data);
	}

	/**
	 * see http://php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 * @throws ForbiddenException
	 */
	public function touch($path, $mtime = null) {
		$this->checkFileAccess($path);
		return $this->storage->touch($path, $mtime);
	}


	/**
	 * @throws ForbiddenException
	 */
	public function writeStream(string $path, $stream, int $size = null): int {
		// Required for object storage since  part file is not in the storage so we cannot check it before moving it to the storage
		// As an alternative we might be able to check on the cache update/insert/delete though the Cache wrapper
		$result = $this->storage->writeStream($path, $stream, $size);
		try {
			$this->checkFileAccess($path);
		} catch (\Exception $e) {
			$this->storage->unlink($path);
			throw $e;
		}
		return $result;
	}
}
