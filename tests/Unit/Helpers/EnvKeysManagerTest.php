<?php

namespace GeoSot\EnvEditor\Tests\Unit\Helpers;

use GeoSot\EnvEditor\EnvEditor;
use GeoSot\EnvEditor\Exceptions\EnvException;
use GeoSot\EnvEditor\Facades\EnvEditor as EnvEditorFacade;
use GeoSot\EnvEditor\Helpers\EnvKeysManager;
use GeoSot\EnvEditor\Tests\TestCase;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;

class EnvKeysManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->app['config']->set('env-editor.paths.env', self::getTestPath());
        $this->app['config']->set('env-editor.envFileName', self::getTestFile());
    }

    #[Test]
    public function check_key_existence(): void
    {
        self::assertTrue($this->getEnvKeysManager()->has('LOG_CHANNEL'));
        self::assertTrue($this->getEnvKeysManager()->has('DB_CONNECTION'));
        self::assertFalse($this->getEnvKeysManager()->has('FOO'));
        self::assertFalse($this->getEnvKeysManager()->has(''));
        self::assertFalse($this->getEnvKeysManager()->has('null'));
    }

    #[Test]
    public function returns_value_or_default(): void
    {
        self::assertEquals('stack', $this->getEnvKeysManager()->get('LOG_CHANNEL'));
        self::assertEquals('mysql', $this->getEnvKeysManager()->get('DB_CONNECTION'));
        self::assertEquals('3306', $this->getEnvKeysManager()->get('DB_PORT'));
        self::assertEquals('', $this->getEnvKeysManager()->get('BROADCAST_DRIVER'));
        self::assertEquals('foo', $this->getEnvKeysManager()->get('BROADCAST_DRIVER', 'foo'));
        self::assertEquals(null, $this->getEnvKeysManager()->get('FOO'));
        self::assertEquals('Bar', $this->getEnvKeysManager()->get('FOO', 'Bar'));
    }

    #[Test]
    public function deletes_keys(): void
    {
        $fileName = 'dummy.tmp';
        $fullPath = $this->createNewDummyFile($fileName);
        $this->app['config']->set('env-editor.envFileName', $fileName);

        self::assertStringContainsString('LOG_CHANNEL', file_get_contents($fullPath));
        self::assertTrue($this->getEnvKeysManager()->delete('LOG_CHANNEL'));
        self::assertStringNotContainsString('LOG_CHANNEL=stack', file_get_contents($fullPath));

        self::assertStringContainsString('CACHE_DRIVER', file_get_contents($fullPath));
        self::assertTrue($this->getEnvKeysManager()->delete('CACHE_DRIVER'));
        self::assertStringNotContainsString('CACHE_DRIVER="file"', file_get_contents($fullPath));

        self::assertStringNotContainsString('CACHE_DRIVER', file_get_contents($fullPath));
        try {
            $this->getEnvKeysManager()->delete('CACHE_DRIVER');
        } catch (\Exception $e) {
            self::assertInstanceOf(EnvException::class, $e);
            unlink($fullPath);
        }
    }

    #[Test]
    public function edits_keys(): void
    {
        $fileName = 'dummy.tmp';
        $fullPath = $this->createNewDummyFile($fileName);
        $this->app['config']->set('env-editor.envFileName', $fileName);

        self::assertStringContainsString('LOG_CHANNEL=stack', file_get_contents($fullPath));
        self::assertTrue($this->getEnvKeysManager()->edit('LOG_CHANNEL', 'foo'));
        self::assertStringContainsString('LOG_CHANNEL=foo', file_get_contents($fullPath));

        self::assertStringContainsString('CACHE_DRIVER="file"', file_get_contents($fullPath));
        self::assertTrue($this->getEnvKeysManager()->edit('CACHE_DRIVER', '"bar"'));
        self::assertStringContainsString('CACHE_DRIVER="bar"', file_get_contents($fullPath));

        self::assertTrue($this->getEnvKeysManager()->edit('CACHE_DRIVER', ''));
        self::assertStringContainsString('CACHE_DRIVER=', file_get_contents($fullPath));

        self::assertTrue($this->getEnvKeysManager()->edit('CACHE_DRIVER', null));
        self::assertStringContainsString('CACHE_DRIVER=', file_get_contents($fullPath));

        self::assertStringNotContainsString('WRONG_KEY', file_get_contents($fullPath));
        try {
            $this->getEnvKeysManager()->edit('WRONG_KEY', 'fail');
        } catch (\Exception $e) {
            self::assertInstanceOf(EnvException::class, $e);
            unlink($fullPath);
        }
    }

    #[Test]
    public function adds_keys(): void
    {
        $fileName = 'dummy.tmp';
        $fullPath = $this->createNewDummyFile($fileName);
        $this->app['config']->set('env-editor.envFileName', $fileName);

        EnvEditorFacade::addKey('FOO', 'bar');
        $this->assertSame('bar', EnvEditorFacade::getKey('FOO'));
        try {
            EnvEditorFacade::addKey('FOO', 'bar2');
        } catch (\Exception $e) {
            self::assertInstanceOf(EnvException::class, $e);
            unlink($fullPath);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function getEnvKeysManager(array $config = []): EnvKeysManager
    {
        $envEditor = new EnvEditor(
            new Repository($config ?: $this->app['config']->get('env-editor')),
            new Filesystem()
        );
        $this->app->singleton(EnvEditor::class, fn () => $envEditor);

        return $envEditor->getKeysManager();
    }

    protected function createNewDummyFile(string $name = 'test.tmp'): string
    {
        $dummyFullPath = self::getTestPath().DIRECTORY_SEPARATOR.$name;

        copy(self::getTestFile(true), $dummyFullPath);

        return $dummyFullPath;
    }
}
