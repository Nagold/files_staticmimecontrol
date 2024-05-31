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

namespace OCA\Files_Staticmimecontrol\Scanner;

use OCA\Files_Staticmimecontrol\AppConfig;
use OCA\Files_Staticmimecontrol\StatusFactory;
use Psr\Log\LoggerInterface;
use OC\Files\Storage\Storage;
use OCP\Files\InvalidPathException;

class MimetypeScanner extends ScannerBase {

    private $mimetypeRules = [];
    private $denyRootByDefault = true;

    protected AppConfig $appConfig;

    /**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	protected $storage;

    public function __construct(LoggerInterface $logger, Storage $storage) {
    #    parent::__construct($config, $logger, $statusFactory);
        $this->storage = $storage;
#        $this->appConfig = $config;
    }

    public function initScanner() {
        parent::initScanner();

        /**
         * reads the json config
         *
         * @return array
         */
        try {
#                $config = \OC::$server->getConfig();
                $datadir = $this->appConfig->getSmcConfigpath();
                $jsonFile = $this->appConfig->getSmcConfigfilename();
        } catch (InvalidPathException $e) {
                $this->logger->error("error reading staticmimecontrol_file config: " . $e->getMessage());
                return;
        }
        if (is_file($jsonFile)) {
            $config = json_decode(file_get_contents($jsonFile), true);
            if (is_array($config) && array_key_exists("rules", $config)) {
                $this->mimetypeRules = $config["rules"];
            }
        }
    }

    protected function checkMimetype() {
        $mimeType = $this->storage->getMimeType($this->fileToCheck);
        $this->status->parseResponse($this->fileToCheck, $mimeType, $this->mimetypeRules);
    }
}
