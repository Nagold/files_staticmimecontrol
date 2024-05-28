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

use OCA\Files_Staticmimecontrol\Item;
use OCA\Files_Staticmimecontrol\Status;

interface IScanner {
        /**
         * @param callable(string): void $callback
         */
        public function setDebugCallback(callable $callback): void;

        public function getStatus();

        /**
         * Synchronous scan
         *
         * @param Item $item
         * @return Status
         */
        public function scan(Item $item): Status;

        /**
         * Async scan - new portion of data is available
         *
         * @param string $data
         */
        public function onAsyncData($data);

        /**
         * Async scan - resource is closed
         *
         * @return Status
         */
        public function completeAsyncScan($path): Status;

        /**
         * Open write handle. etc
         */
        public function initScanner();
}
