<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Test\Unit\Model;

use BigCartEnergy\SessionReaperPatch\Model\RequestLogger;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Webapi\Request as HttpRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The assertions stay within what every PHPUnit from 6.5 (Magento 2.3.0) to 12 (Magento 2.4.9) offers.
 */
class RequestLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(RemoteAddress::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
    }

    public function testTheLineCarriesTimeAddressMethodPathTypeAndParameterNamesOnly(): void
    {
        $lines = [];
        $logger = $this->stub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) use (&$lines): void {
            $lines[] = (string)$message;
        });
        $request = $this->stub(HttpRequest::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getRequestUri')->willReturn('/rest/V1/example/path?page=2&secret=1');

        $requestLogger = new RequestLogger($logger, $this->remoteAddress('203.0.113.5'), $request);
        $requestLogger->logStripped('Vendor\\Module\\Api\\Data\\NoteInterface', [
            'attachment' => 'Vendor\\Module\\Model\\Attachment',
            'data' => 'array[]',
        ]);

        self::assertCount(1, $lines);
        $line = $lines[0];
        self::assertSame(1, preg_match('/^time=\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z /', $line), $line);
        $this->assertHas(' ip=203.0.113.5 ', $line);
        $this->assertHas(' method=POST ', $line);
        $this->assertHas(' path=/rest/V1/example/path ', $line);
        $this->assertLacks('page=2', $line);
        $this->assertLacks('secret', $line);
        $this->assertHas(
            'stripped type=Vendor\\Module\\Api\\Data\\NoteInterface'
            . ' parameters=attachment:Vendor\\Module\\Model\\Attachment,data:array[]',
            $line
        );
    }

    public function testARequestThatIsNotAnHttpRequestLogsPlaceholders(): void
    {
        $lines = [];
        $logger = $this->stub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) use (&$lines): void {
            $lines[] = (string)$message;
        });
        $requestLogger = new RequestLogger(
            $logger,
            $this->remoteAddress(false),
            $this->stub(RequestInterface::class)
        );
        $requestLogger->logStripped('Vendor\\Thing', ['x' => 'Vendor\\Other']);

        self::assertCount(1, $lines);
        $this->assertHas(' ip=- method=- path=- ', $lines[0]);
    }

    public function testALoggingFailureIsSwallowedAndNotRetried(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->willThrowException(new \RuntimeException('disk full'));
        $requestLogger = new RequestLogger(
            $logger,
            $this->remoteAddress('203.0.113.5'),
            $this->stub(HttpRequest::class)
        );

        $requestLogger->logStripped('Vendor\\Thing', ['x' => 'Vendor\\Other']);
        $requestLogger->logStripped('Vendor\\Thing', ['x' => 'Vendor\\Other']);
        self::assertTrue(true, 'no exception reached the caller');
    }

    /**
     * @param string|false $address
     * @return RemoteAddress
     */
    private function remoteAddress($address): RemoteAddress
    {
        $remoteAddress = $this->stub(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn($address);
        return $remoteAddress;
    }

    /**
     * A test double without expectations: a stub where the runner offers one, a mock on older runners.
     *
     * @param string $class
     * @return \PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\Stub
     */
    private function stub(string $class)
    {
        return method_exists($this, 'createStub') ? $this->createStub($class) : $this->createMock($class);
    }

    private function assertHas(string $needle, string $haystack): void
    {
        self::assertNotFalse(strpos($haystack, $needle), 'expected "' . $needle . '" in: ' . $haystack);
    }

    private function assertLacks(string $needle, string $haystack): void
    {
        self::assertFalse(strpos($haystack, $needle), 'did not expect "' . $needle . '" in: ' . $haystack);
    }
}
