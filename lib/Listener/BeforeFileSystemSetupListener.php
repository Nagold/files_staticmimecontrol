<?php

namespace OCA\FilesStaticmimecontrol\Listener;

use OC\Files\Filesystem;
use OCA\FilesStaticmimecontrol\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class BeforeFileSystemSetupListener implements IEventListener {
	private Application $app;

	public function __construct(Application $app) {
		$this->app = $app;
	}

	public function handle(Event $event): void {
		Filesystem::addStorageWrapper('files_staticmimecontrol', [$this->app, 'addStorageWrapperCallback'], -10);
	}
}
