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

use OC\Files\Storage\Storage;
use OCA\Files_Staticmimecontrol\AppConfig;
use OCA\Files_Staticmimecontrol\Item;
use OCA\Files_Staticmimecontrol\Status;
use OCA\Files_Staticmimecontrol\StatusFactory;
use Psr\Log\LoggerInterface;

abstract class ScannerBase implements IScanner {
        /**
         * Scan result
         */
        protected Status $status;

        /**
         * If scanning was done part by part
         * the first detected infected part is stored here
         */
        protected ?Status $allowedStatus = null;

        protected int $byteCount;

        /** @var  resource */
        protected $writeHandle;

        protected AppConfig $appConfig;
        protected LoggerInterface $logger;
        protected StatusFactory $statusFactory;
        protected ?string $lastChunk = null;
        protected bool $isLogUsed = false;
        protected bool $isAborted = false;
        
        protected $fileToCheck;
        protected $mimeType;

        public function __construct(LoggerInterface $logger, Storage $storage) {
                $this->appConfig = New AppConfig;
                $this->logger = $logger;
                $this->statusFactory = New StatusFactory($this->logger);
                $this->status = $this->statusFactory->newStatus();
        }


        /**
         * @return Status
         */
        public function getStatus() {
                if ($this->allowedStatus instanceof Status) {
                        return $this->allowedStatus;
                }
                return $this->status;
        }

        /**
         * Synchronous scan
         *
         * @param Item $item
         * @return Status
         */
        public function scan(Item $item): Status {
                $this->initScanner();
                return $this->getStatus();
        }


        /**
         *       * Async scan - new portion of data is available
         *       *
         *
         * @param string $data
         *
         * @return void
         */
        public function onAsyncData($data) {
#                $this->writeChunk($data);
        }

        /**
         * Async scan - resource is closed
         *
         * @return Status
         */
        public function completeAsyncScan($path): Status {
                $this->fileToCheck = $path;
                return $this->getStatus();
        }


        /**
         *       * Open write handle. etc
         *
         * @return void
         */
        public function initScanner() {
                $this->byteCount = 0;
                if ($this->status->getNumericStatus() === Status::MIMETYPE_DISALLOWED) {
                        $this->allowedStatus = clone $this->status;
                }
                $this->status = $this->statusFactory->newStatus();
        }

        public function setDebugCallback(callable $callback): void {
                // unsupported
        }
}
