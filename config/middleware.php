<?php

declare(strict_types=1);

use App\Handler\HttpErrorHandler;
use App\Handler\ServerErrorHandler;
use App\Middleware\CorsMiddleware;
use App\Middleware\CorrelationIdMiddleware;
use App\Middleware\LanguageMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RedirectMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\TrailingSlashMiddleware;
use Slim\App;
use Slim\Exception\HttpException;

return static function (App $app): void {
    $settings = $app->getContainer()->get('settings');
    $displayErrorDetails = (bool) ($settings['twig']['debug'] ?? false);
    $container = $app->getContainer();

    $app->add(CorrelationIdMiddleware::class);
    $app->add(SecurityHeadersMiddleware::class);
    $app->add($container->get(CorsMiddleware::class));
    $app->addBodyParsingMiddleware();
    $app->add($container->get(RateLimitMiddleware::class));
    $app->add(LanguageMiddleware::class);
    $app->add(RedirectMiddleware::class);
    $app->add(TrailingSlashMiddleware::class);

    $app->addRoutingMiddleware();

    $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
    $errorMiddleware->setErrorHandler(HttpException::class, $container->get(HttpErrorHandler::class), true);
    $errorMiddleware->setDefaultErrorHandler($container->get(ServerErrorHandler::class));
};
