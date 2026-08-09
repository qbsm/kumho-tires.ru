<?php

declare(strict_types=1);

use App\Action\ApiFormTokenAction;
use App\Action\ApiSendAction;
use App\Action\ApiWidgetRescueAction;
use App\Action\HealthAction;
use App\Action\PageAction;
use App\Action\SitemapAction;
use Slim\App;

return static function (App $app): void {
    $app->get('/health', HealthAction::class);
    $app->get('/api/form-token[/]', ApiFormTokenAction::class);
    $app->post('/api/send[/]', ApiSendAction::class);
    $app->post('/api/widget-rescue[/]', ApiWidgetRescueAction::class);
    $app->map(['GET', 'POST'], '/_f', static fn($request, $response) => $response->withStatus(204));
    $app->get('/sitemap.xml', SitemapAction::class);
    $app->get('/', PageAction::class);
    $app->get('/{page}[/{params:.*}]', PageAction::class);
};
