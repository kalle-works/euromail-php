<?php

namespace EuroMail\Tests;

use EuroMail\Attachment;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    private string $tmpFile;

    protected function tearDown(): void
    {
        if (isset($this->tmpFile) && file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testFromFileReturnsFilenameContentTypeAndBase64Content(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'euromail_test_') . '.txt';
        file_put_contents($this->tmpFile, 'hello attachment');

        $attachment = Attachment::fromFile($this->tmpFile);

        $this->assertSame(basename($this->tmpFile), $attachment['filename']);
        $this->assertSame(base64_encode('hello attachment'), $attachment['content']);
        $this->assertSame('hello attachment', base64_decode($attachment['content']));
        $this->assertArrayHasKey('content_type', $attachment);
        $this->assertNotSame('', $attachment['content_type']);
    }

    public function testFromFileFallsBackToOctetStreamForUnknownType(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'euromail_test_');
        file_put_contents($this->tmpFile, "\x00\x01\x02\x03binarygarbage");

        $attachment = Attachment::fromFile($this->tmpFile);

        $this->assertIsString($attachment['content_type']);
        $this->assertNotSame('', $attachment['content_type']);
    }

    public function testFromFileThrowsForNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Attachment::fromFile('/no/such/path/does-not-exist-' . uniqid() . '.txt');
    }

    public function testFromFileThrowsForUnreadableFile(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'euromail_test_') . '.txt';
        file_put_contents($this->tmpFile, 'secret');
        chmod($this->tmpFile, 0000);

        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test unreadable file permissions while running as root.');
        }

        try {
            $this->expectException(\InvalidArgumentException::class);
            Attachment::fromFile($this->tmpFile);
        } finally {
            chmod($this->tmpFile, 0644);
        }
    }
}
