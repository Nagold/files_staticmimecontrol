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

use OCP\IConfig;

class AppConfig {
    private $appName = 'files_staticmimecontrol';

    private $config;

    private $defaults = [
        'smc_configfilename' => 'staticmimecontrol.json',
        'smc_configpath' => '/data/',
        ];
 
    /**
     * Does all the someConfig to some_config magic
     *
     * @param string $property
     * @return string
     */
    protected function propertyToKey(string $property): string {
        $parts = preg_split('/(?=[A-Z])/', $property);
        $column = '';

        foreach ($parts as $part) {
            if ($column === '') {
                $column = $part;
            } else {
                $column .= '_' . lcfirst($part);
            }
        }
        return $column;
    }

    	/**
	 * 	 * Set a value with magic __call invocation
	 * 	 *
	 *
	 * @param string $key
	 * @param array $args
	 *
	 * @throws \BadFunctionCallException
	 */
	protected function setter(string $key, array $args): void {
		if (array_key_exists($key, $this->defaults)) {
			$this->setAppValue($key, $args[0]);
		} else {
			throw new \BadFunctionCallException($key . ' is not a valid key');
		}
	}

	/**
	 * Get a value with magic __call invocation
	 *
	 * @param string $key
	 * @return ?string
	 * @throws \BadFunctionCallException
	 */
	protected function getter(string $key): ?string {
		if (array_key_exists($key, $this->defaults)) {
			return $this->getAppValue($key);
		}

		throw new \BadFunctionCallException($key . ' is not a valid key');
	}

    /**
     * Get/set an option value by calling getSomeOption method
     *
     * @param string $methodName
     * @param array $args
     * @return ?string
     * @throws \BadFunctionCallException
     */
    public function __call(string $methodName, array $args): ?string {
        $attr = lcfirst(substr($methodName, 3));
        $key = $this->propertyToKey($attr);
        if (strpos($methodName, 'set') === 0) {
            $this->setter($key, $args);
            return null;
        } elseif (strpos($methodName, 'get') === 0) {
            return $this->getter($key);
        } else {
            throw new \BadFunctionCallException($methodName .
                ' does not exist');
        }
    }
}
