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

use Psr\Log\LoggerInterface;

class Status {

    /*
     * The file was not checked
     */
    public const MIMETYPE_UNCHECKED = -1;
    
    /*
     * The files was checked and found good
     */
    public const MIMETYPE_ALLOWED = 0;
    
    /*
     * The file was checked and found bad
     */
    public const MIMETYPE_DISALLOWED = 1;
    
    protected $numericStatus = self::MIMETYPE_UNCHECKED;

    protected $details = '';
    
    protected LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    /**
      * Get scan status as integer
      * @return int
      */
    public function getNumericStatus(): int {
        return $this->numericStatus;
    }

    public function getDetails() {
        return $this->details;
    }

    public function setNumericStatus(int $numericStatus): void {
        $this->numericStatus = $numericStatus;
    }

    public function setDetails(string $details): void {
        $this->details = $details;
    }

    public function parseResponse($path, $mime, $mimetyperules) {
        if (!$mime || $mime == "httpd/unix-directory") {
            $this->numericStatus = self::MIMETYPE_ALLOWED;
        } else {
            $filtered = array_filter($mimetyperules, function ($value) use ($path, $mime) {
                $pathmatch = preg_match('%' . $value["path"] . '%', $path);
                $mimematch = preg_match('%' . $value["mime"] . '%', $mime);
                return ($pathmatch && $mimematch);
            });

            if (count($filtered) === 0) {
                $this->numericStatus = self::MIMETYPE_ALLOWED;
                $this->details = '';
            } else {
                $this->numericStatus = self::MIMETYPE_DISALLOWED;
                $this->details = 'Mime type "' . $mime . '" not allowed for "' . $path . '".';
            }
        }
    }
}
