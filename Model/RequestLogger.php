<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\SessionReaperPatch\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\HTTP\PhpEnvironment\Request as HttpRequest;
use Psr\Log\LoggerInterface;

/**
 * Writes one line per conversion with removed keys to the module's log file: the UTC time, the client IP
 * address, the request method and path, the type being converted and the removed parameter names with their
 * types. The request body is never logged. A logging failure is swallowed and stops further attempts in the
 * same process, so the guard itself is never affected by it.
 */
class RequestLogger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var bool
     */
    private $failed = false;

    /**
     * @param LoggerInterface $logger The module's own logger (see etc/di.xml)
     * @param RemoteAddress $remoteAddress
     * @param RequestInterface $request
     */
    public function __construct(LoggerInterface $logger, RemoteAddress $remoteAddress, RequestInterface $request)
    {
        $this->logger = $logger;
        $this->remoteAddress = $remoteAddress;
        $this->request = $request;
    }

    /**
     * Logs the keys removed from one conversion.
     *
     * @param string $type The type the processor was asked to convert to
     * @param string[] $parameters The removed keys as [parameter name => parameter type]
     * @return void
     */
    public function logStripped(string $type, array $parameters): void
    {
        $pairs = [];
        foreach ($parameters as $name => $parameterType) {
            $pairs[] = $name . ':' . $parameterType;
        }
        $this->write(sprintf('stripped type=%s parameters=%s', $type, implode(',', $pairs)));
    }

    /**
     * Writes one line; never throws.
     *
     * @param string $event
     * @return void
     */
    private function write(string $event): void
    {
        if ($this->failed) {
            return;
        }
        try {
            $this->logger->warning($this->requestLine() . ' ' . $event);
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    /**
     * The UTC time, the client IP address and the request method and path, without the query string.
     *
     * @return string
     */
    private function requestLine(): string
    {
        $address = $this->remoteAddress->getRemoteAddress();
        $method = '-';
        $path = '-';
        if ($this->request instanceof HttpRequest) {
            $method = (string)$this->request->getMethod();
            $path = explode('?', (string)$this->request->getRequestUri(), 2)[0];
        }
        return sprintf(
            'time=%s ip=%s method=%s path=%s',
            gmdate('Y-m-d\TH:i:s\Z'),
            is_string($address) && $address !== '' ? $address : '-',
            $method !== '' ? $method : '-',
            $path !== '' ? $path : '-'
        );
    }
}
