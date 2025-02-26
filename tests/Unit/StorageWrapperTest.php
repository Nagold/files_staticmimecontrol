<?php

namespace OCA\FilesStaticmimecontrol\Tests;

use OCA\FilesStaticmimecontrol\StorageWrapper;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use Test\TestCase;

class StorageWrapperTest extends TestCase {
    private $storageMock;
    private $configMock;
    private $storageWrapper;

    protected function setUp(): void {
        parent::setUp();

        // Mock Storage
        $this->storageMock = $this->createMock(IStorage::class);

        // Mock Config with JSON rules
        $this->configMock = $this->createMock(IConfig::class);
        $this->configMock->method('getSystemValue')
            ->willReturn(json_encode([
                "denyrootbydefault" => true,
                "rules" => [
                    ["path" => ".*", "mime" => "image\\/jpeg"],
                    ["path" => "Folder1", "mime" => "image.*"],
                    ["path" => "Folder2", "mime" => "image\\/png"],
                    ["path" => "Folder3.*", "mime" => "text.*"],
                    ["path" => "^__groupfolders\\/1(\\/.*)?$", "mime" => "text.*"],
                    ["path" => "^__groupfolders\\/(1|2)(\\/.*)?$", "mime" => "image\\/png"],
                    ["path" => "^(?!__groupfolders\\/8(\\/|$)).*$", "mime" => "application\\/zip"]
                ]
            ]));

        // Instantiate StorageWrapper with mocks
        $this->storageWrapper = new StorageWrapper(
            ['storage' => $this->storageMock],
            $this->storageMock,
            $this->configMock
        );
    }

    /**
     * @dataProvider fileUploadProvider
     */
    public function testFileUploadPermissions($path, $mimeType, $expected): void {
        $this->storageMock->method('getMimeType')->willReturn($mimeType);
        $result = $this->storageWrapper->isCreatable($path);

        $this->assertSame($expected, $result, "Failed for path: $path, mime: $mimeType");
    }

    public static function fileUploadProvider(): array {
        return [
            // Root uploads
            ['files/test.jpg', 'image/jpeg', true],
            ['files/test.png', 'image/png', false],
            ['files/test.txt', 'text/plain', false],
            ['files/test.zip', 'application/zip', true],

            // Folder1
            ['files/Folder1/test.jpg', 'image/jpeg', true],
            ['files/Folder1/test.png', 'image/png', true],
            ['files/Folder1/test.txt', 'text/plain', false],
            ['files/Folder1/test.zip', 'application/zip', true],

            // Folder2
            ['files/Folder2/test.jpg', 'image/jpeg', true],
            ['files/Folder2/test.png', 'image/png', true],
            ['files/Folder2/test.txt', 'text/plain', false],
            ['files/Folder2/test.zip', 'application/zip', true],

            // Folder2/subfolder2
            ['files/Folder2/subfolder2/test.jpg', 'image/jpeg', true],
            ['files/Folder2/subfolder2/test.png', 'image/png', false],
            ['files/Folder2/subfolder2/test.txt', 'text/plain', false],
            ['files/Folder2/subfolder2/test.zip', 'application/zip', true],

            // Folder3
            ['files/Folder3/test.jpg', 'image/jpeg', true],
            ['files/Folder3/test.png', 'image/png', false],
            ['files/Folder3/test.txt', 'text/plain', true],
            ['files/Folder3/test.zip', 'application/zip', true],

            // Groupfolders
            ['__groupfolders/1/test.jpg', 'image/jpeg', true],
            ['__groupfolders/1/test.png', 'image/png', true],
            ['__groupfolders/1/test.txt', 'text/plain', true],
            ['__groupfolders/1/test.zip', 'application/zip', true],

            ['__groupfolders/1/subfolder1/test.jpg', 'image/jpeg', true],
            ['__groupfolders/1/subfolder1/test.png', 'image/png', true],
            ['__groupfolders/1/subfolder1/test.txt', 'text/plain', true],
            ['__groupfolders/1/subfolder1/test.zip', 'application/zip', true],

            ['__groupfolders/2/test.jpg', 'image/jpeg', true],
            ['__groupfolders/2/test.png', 'image/png', true],
            ['__groupfolders/2/test.txt', 'text/plain', false],
            ['__groupfolders/2/test.zip', 'application/zip', true],

            ['__groupfolders/10/test.jpg', 'image/jpeg', true],
            ['__groupfolders/10/test.png', 'image/png', false],
            ['__groupfolders/10/test.txt', 'text/plain', false],
            ['__groupfolders/10/test.zip', 'application/zip', true],

            ['__groupfolders/10/subfolder10/test.jpg', 'image/jpeg', true],
            ['__groupfolders/10/subfolder10/test.png', 'image/png', false],
            ['__groupfolders/10/subfolder10/test.txt', 'text/plain', false],
            ['__groupfolders/10/subfolder10/test.zip', 'application/zip', true],

            ['__groupfolders/8/test.jpg', 'image/jpeg', true],
            ['__groupfolders/8/test.png', 'image/png', false],
            ['__groupfolders/8/test.txt', 'text/plain', false],
            ['__groupfolders/8/test.zip', 'application/zip', false],

            ['__groupfolders/8/subfolder8/test.jpg', 'image/jpeg', true],
            ['__groupfolders/8/subfolder8/test.png', 'image/png', false],
            ['__groupfolders/8/subfolder8/test.txt', 'text/plain', false],
            ['__groupfolders/8/subfolder8/test.zip', 'application/zip', false],
        ];
    }
}
