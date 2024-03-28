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

use OC\Files\Storage\Wrapper\Wrapper;
use OCA\Files_Staticmimecontrol\Activity\Provider;
use OCA\Files_Staticmimecontrol\AppInfo\Application;
use OCA\Files_Staticmimecontrol\Event\ScanStateEvent;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\Activity\IManager as ActivityManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\InvalidContentException;
use OCP\IL10N;
#use Psr\Log\LoggerInterface;

class MimetypeWrapper extends Wrapper {
        /**
         * Modes that are used for writing
         * @var array
         */
        private $writingModes = ['r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'];

        /** @var IL10N */
        protected $l10n;

        /** @var LoggerInterface */
        protected $logger;

        /** @var ActivityManager */
        protected $activityManager;

        /** @var bool */
        protected $isHomeStorage;

        /** @var bool */
        private $shouldScan = true;

        /** @var bool */
        private $trashEnabled;

        /**
         * @param array $parameters
         */
        public function __construct($parameters) {
                parent::__construct($parameters);
                $this->l10n = $parameters['l10n'];
                $this->logger = $parameters['logger'];
                $this->activityManager = $parameters['activityManager'];
                $this->isHomeStorage = $parameters['isHomeStorage'];
                $this->trashEnabled = $parameters['trashEnabled'];

                /** @var IEventDispatcher $eventDispatcher */
                $eventDispatcher = $parameters['eventDispatcher'];
                $eventDispatcher->addListener(ScanStateEvent::class, function (ScanStateEvent $event) {
                        $this->shouldScan = $event->getState();
                });
        }

        /**
         * Asynchronously scan data that are written to the file
         * @param string $path
         * @param string $mode
         * @return resource | false
         */
        public function fopen($path, $mode) {
                $stream = $this->storage->fopen($path, $mode);

                /*
                 * Only check when
                 *  - it is a resource
                 *  - it is a writing mode
                 *  - if it is a homestorage it starts with files/
                 *  - if it is not a homestorage we always wrap (external storages)
                 */
                if ($this->shouldWrap($path) && is_resource($stream) && $this->isWritingMode($mode)) {
                        $stream = $this->wrapStream($path, $stream);
                }
                return $stream;
        }

        public function writeStream(string $path, $stream, int $size = null): int {
                if ($this->shouldWrap($path)) {
                        $stream = $this->wrapStream($path, $stream);
                }
                return parent::writeStream($path, $stream, $size);
        }

        private function shouldWrap(string $path): bool {
                return $this->shouldScan
                        && (!$this->isHomeStorage
                                || (strpos($path, 'files/') === 0
                                        || strpos($path, '/files/') === 0)
                        );
        }

        private function wrapStream(string $path, $stream) {
                $scanner = new MimetypeScanner($appData, $logger, $statusFactory);
                try {
                        return CallbackReadDataWrapper::wrap(
                                $stream,
                                function ($count, $data) use ($scanner) {
                                        $scanner->onAsyncData($data);
                                },
                                function ($data) use ($scanner) {
                                        $scanner->onAsyncData($data);
                                },
                                function () use ($path) {
                                        $status = $scanner->completeAsyncScan($path);
                                        if ($status->getNumericStatus() === Status::MIMETYPE_DISALLOWED) {
                                                //prevent from going to trashbin
                                                if ($this->trashEnabled) {
                                                        /** @var ITrashManager $trashManager */
                                                        $trashManager = \OC::$server->query(ITrashManager::class);
                                                        $trashManager->pauseTrash();
                                                }

                                                $owner = $this->getOwner($path);
                                                $this->unlink($path);

                                                if ($this->trashEnabled) {
                                                        /** @var ITrashManager $trashManager */
                                                        $trashManager = \OC::$server->query(ITrashManager::class);
                                                        $trashManager->resumeTrash();
                                                }

                                                $this->logger->warning(
                                                        'Disallowed mime type found: file deleted. ' . $status->getDetails()
                                                        . ' Account: ' . $owner . ' Path: ' . $path,
                                                        ['app' => 'files_staticmimecontrol']
                                                );

                                                $activity = $this->activityManager->generateEvent();
                                                $activity->setApp(Application::APP_NAME)
                                                        ->setSubject(Provider::SUBJECT_VIRUS_DETECTED_UPLOAD, [$status->getDetails()])
                                                        ->setMessage(Provider::MESSAGE_FILE_DELETED)
                                                        ->setObject('', 0, $path)
                                                        ->setAffectedUser($owner)
                                                        ->setType(Provider::TYPE_VIRUS_DETECTED);
                                                $this->activityManager->publish($activity);

                                                $this->logger->error('Disallowed mime type found, file deleted. ' . $status->getDetails() .
                                                        ' File: ' . $path . ' Account: ' . $owner, ['app' => 'files_staticmimecontrol']);

                                                throw new InvalidContentException(
                                                        $this->l10n->t(
                                                                'Mime type error %s. Upload cannot be completed.',
                                                                $status->getDetails()
                                                        )
                                                );
                                        }
                                }
                        );
                } catch (\Exception $e) {
                        $this->logger->error($e->getMessage(), ['exception' => $e]);
                }
                return $stream;
        }

        /**
         * Checks whether passed mode is suitable for writing
         * @param string $mode
         * @return bool
         */
        private function isWritingMode($mode) {
                // Strip unessential binary/text flags
                $cleanMode = str_replace(
                        ['t', 'b'],
                        ['', ''],
                        $mode
                );
                return in_array($cleanMode, $this->writingModes);
        }
}
