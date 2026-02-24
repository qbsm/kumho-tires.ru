<?php

namespace App\Support;

use Psr\Http\Message\ServerRequestInterface;

final class BaseUrlResolver
{
    public function resolve(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';
        $host = $uri->getHost() !== '' ? $uri->getHost() : 'localhost';
        $port = $uri->getPort();

        $authority = $host;
        if ($port !== null && !in_array([$scheme, $port], [['http', 80], ['https', 443]], true)) {
            $authority .= ':' . $port;
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
        $scriptDir = dirname($scriptName);
        $basePath = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');

        return $scheme . '://' . $authority . $basePath . '/';
    }
}
