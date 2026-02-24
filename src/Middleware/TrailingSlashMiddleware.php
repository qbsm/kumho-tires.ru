<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TrailingSlashMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($path !== '/' && !str_ends_with($path, '/') && strpos((string) basename($path), '.') === false) {
            $target = (string) $uri->withPath($path . '/');
            $response = $this->responseFactory->createResponse(301);
            return $response->withHeader('Location', $target);
        }

        return $handler->handle($request);
    }
}
