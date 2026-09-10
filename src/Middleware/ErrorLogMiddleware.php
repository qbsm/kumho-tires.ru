<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Middleware\CorrelationIdMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Логирует каждый ответ со статусом >= 400 с контекстом запроса, независимо от того,
 * каким обработчиком он получен (HttpErrorHandler, ServerErrorHandler, PageAction).
 *
 * Уровни подобраны так, чтобы прод (WARNING) показывал только значимое:
 * - 5xx — error;
 * - 404 с реферером своего домена — warning (битая внутренняя ссылка);
 * - остальные 4xx и 404 от ботов/внешних ссылок — info.
 */
final class ErrorLogMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $status = $response->getStatusCode();

        if ($status < 400) {
            return $response;
        }

        $uri = $request->getUri();
        $referer = $request->getHeaderLine('Referer');
        $internalReferer = $referer !== '' && str_contains($referer, $uri->getHost());

        $level = match (true) {
            $status >= 500 => LogLevel::ERROR,
            $status === 404 && $internalReferer => LogLevel::WARNING,
            default => LogLevel::INFO,
        };

        // Middleware самый внешний, поэтому в запросе атрибута ещё нет — id берём из
        // заголовка ответа, который проставил CorrelationIdMiddleware внутри стека.
        $requestId = $response->getHeaderLine('X-Request-Id');
        if ($requestId === '') {
            $requestId = (string) $request->getAttribute(CorrelationIdMiddleware::REQUEST_ATTRIBUTE, '');
        }

        $this->logger->log($level, sprintf('HTTP %d %s %s', $status, $request->getMethod(), $uri->getPath()), [
            'request_id' => $requestId,
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'referer' => $referer,
            'internal_referer' => $internalReferer,
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'ip' => $this->clientIp($request),
        ]);

        return $response;
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        $server = $request->getServerParams();

        return (string) ($server['REMOTE_ADDR'] ?? '');
    }
}
