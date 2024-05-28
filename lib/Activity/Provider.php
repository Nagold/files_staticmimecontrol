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

namespace OCA\Files_Staticmimecontrol\Activity;

use OCA\Files_Staticmimecontrol\AppInfo\Application;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class Provider implements IProvider
{

    public const TYPE_ILLEGALMIMETYPE_DETECTED = 'illegal_mimetype_detected';

    public const SUBJECT_ILLEGALMIMETYPE_DETECTED = 'illegal_mimetype_detected';

    public const MESSAGE_FILE_DELETED = 'file_deleted';

    /** @var IFactory */
    private $languageFactory;

    /** @var IURLGenerator */
    private $urlGenerator;

    public function __contruct(IFactory $languageFactory, IURLGenerator $urlGenerator)
    {
        $this->languageFactory = $languageFactory;
        $this->urlGenerator = $urlGenerator;
    }

    public function parse($language, IEvent $event, IEvent $previousEvent = null)
    {
        if ($event->getApp() !== Application::APP_NAME || $event->getType() !== self::TYPE_ILLEGALMIMETYPE_DETECTED) {
            throw new \InvalidArgumentException();
        }

        $l = $this->languageFactory->get('files_staticmimecontrol', $language);

        $parameters = [];
        $subject = '';

        if ($event->getSubject() === self::SUBJECT_ILLEGALMIMETYPE_DETECTED) {
            $subject = $l->t('File {file} has illegal mime type {mimetype}');

            $params = $event->getSubjectParameters();
            $parameters['mimetype'] = [
                'type' => 'highlight',
                'id' => $params[1],
                'name' => $params[1],
            ];

            $parameters['file'] = [
                'type' => 'highlight',
                'id' => $event->getObjectName(),
                'name' => basename($event->getObjectName()),
            ];

            if ($event->getMessage() === self::MESSAGE_FILE_DELETED) {
                $event->setParsedMessage($l->t('The file has been removed'));
            }

            $this->setSubjects($event, $subject, $parameters);

            return $event;
        }
    }

    private function setSubjects(IEvent $event, string $subject, array $parameters): void
    {
        $placeholders = $replacements = [];
        foreach ($parameters as $placeholder => $parameter) {
            $placeholders[] = '{' . $placeholder . '}';
            if ($parameter['type'] === 'file') {
                $replacements[] = $parameter['path'];
            } else {
                $replacements[] = $parameter['name'];
            }
        }

        $event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
            ->setRichSubject($subject, $parameters);
    }
}
