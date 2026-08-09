<?php

$projectRoot = dirname(__DIR__);

// APP_ENV: production | development — разделение окружений (кэш Twig, уровень логов)
$appEnv = (string) (getenv('APP_ENV') ?: 'development');
$isProduction = $appEnv === 'production';

$debugValue = (string) (getenv('APP_DEBUG') ?: ($isProduction ? '0' : '1'));
$isDebug = in_array(strtolower($debugValue), ['1', 'true', 'yes', 'on'], true);

$cacheDir = $projectRoot . '/cache';

// Единый источник языков — data/json/global.json → lang (code, title, direction)
$jsonGlobalPath = $projectRoot . '/data/json/global.json';
$available_langs = ['ru', 'en'];
$default_lang = (string) (getenv('APP_DEFAULT_LANG') ?: 'ru');
if (is_readable($jsonGlobalPath)) {
    $global = json_decode((string) file_get_contents($jsonGlobalPath), true);
    if (isset($global['lang']) && is_array($global['lang'])) {
        $available_langs = array_values(array_filter(array_map(
            static function ($item) {
                return is_array($item) && isset($item['code']) ? (string) $item['code'] : null;
            },
            $global['lang']
        )));
        if ($available_langs === []) {
            $available_langs = ['ru', 'en'];
        }
        if (!getenv('APP_DEFAULT_LANG') && isset($global['lang'][0]['code'])) {
            $default_lang = (string) $global['lang'][0]['code'];
        }
    }
}
$envDefaultLang = getenv('APP_DEFAULT_LANG');
if ($envDefaultLang !== false && $envDefaultLang !== '') {
    $default_lang = (string) $envDefaultLang;
}

// Ключи и ширины для адаптивных изображений (picture.twig, tools/build) — единый источник
// Проектная конфигурация (route_map, collections, sitemap_pages, integrations)
$projectConfigPath = __DIR__ . '/project.php';
$projectConfig = is_file($projectConfigPath) ? (array) require $projectConfigPath : [];

$imageSizesPath = __DIR__ . '/image-sizes.json';
$image_sizes = [
    'keys' => ['800', '1600', 'raw'],
    'widths' => ['800' => 800, '1600' => 1600, 'raw' => null],
];
if (is_readable($imageSizesPath)) {
    $imageSizesData = json_decode((string) file_get_contents($imageSizesPath), true);
    if (is_array($imageSizesData)) {
        if (isset($imageSizesData['keys']) && is_array($imageSizesData['keys'])) {
            $image_sizes['keys'] = array_values($imageSizesData['keys']);
        }
        if (isset($imageSizesData['widths']) && is_array($imageSizesData['widths'])) {
            $image_sizes['widths'] = $imageSizesData['widths'];
        }
    }
}

return [
    'project_root' => $projectRoot,
    'env' => $appEnv,
    'debug' => $isDebug,
    'default_lang' => $default_lang,
    'available_langs' => $available_langs,
    'yandex_metric_id' => (int) (getenv('YANDEX_METRIC_ID') ?: 0),
    // Cache-busting изображений: ?v=<версия> к путям data/ и assets/ (см. UrlExtension).
    // Бампится вручную при замене картинки под тем же именем.
    'img_cache_version' => (string) (getenv('IMG_CACHE_VERSION') ?: '1'),
    // slug в URL => page_id (из project.php)
    'route_map' => (array) ($projectConfig['route_map'] ?? []),
    // Конфигурация коллекций (из project.php)
    'collections' => (array) ($projectConfig['collections'] ?? []),
    // page_id страниц для sitemap.xml (из project.php)
    'sitemap_pages' => (array) ($projectConfig['sitemap_pages'] ?? ['index']),
    // Динамические подпути для sitemap (из project.php): page => {data_page, list_key, value_key, slugger}
    'sitemap_dynamic_pages' => (array) ($projectConfig['sitemap_dynamic_pages'] ?? []),
    // Дополнительные статические адреса sitemap (страницы фильтра каталога)
    'sitemap_extra_paths' => (array) ($projectConfig['sitemap_extra_paths'] ?? []),
    // Rate limiting для POST /api/send (по IP, файловое хранилище в cache/rate_limit)
    'rate_limit_api_send' => [
        'paths' => ['/api/send', '/api/widget-rescue'],
        'max_requests' => 10,
        'window_seconds' => 60,
    ],
    // Токен формы выдаётся браузеру по запросу, а не вместе с HTML: страница, скачанная
    // роботом, не даёт возможности отправить заявку. min_age — сколько секунд между выдачей
    // токена и отправкой считаем нижней границей для живого человека.
    'form_token' => [
        'min_age' => 3,
        'max_age' => 7200,
        'secret_file' => $cacheDir . '/form-token-secret',
    ],
    'cors' => [
        'allowed_origins' => [], // например ['https://example.com'] или ['*'] для любого
        'allowed_methods' => ['GET', 'POST', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],
        'allow_credentials' => false,
    ],
    'mail' => [
        // Пусто — флага в .env нет, поведение прежнее: канал включён, если задан адрес.
        'enable' => (string) (getenv('MAIL_ENABLE') ?: ''),
        'dsn' => (string) (getenv('MAIL_DSN') ?: 'sendmail://default'),
        'to' => (string) (getenv('MAIL_TO') ?: ''),
        'from' => (string) (getenv('MAIL_FROM') ?: 'noreply@localhost'),
        'from_name' => (string) (getenv('MAIL_FROM_NAME') ?: ''),
        'subject_prefix' => (string) (getenv('MAIL_SUBJECT_PREFIX') ?: ''),
    ],
    // Резервный сбор заявок (rescue-канал): дублирует заявку в наш сервис, который сначала её
    // сохраняет, а потом раздаёт по каналам с повторами — упавший канал не теряет лид.
    // Подтверждение отправителя — по домену: заявку шлёт бэкенд, значит с адреса, на который
    // домен резолвится. Секрета в .env нет; ключ нужен только хостингам вне нашего периметра.
    'rescue' => [
        'enable' => filter_var((string) (getenv('RESCUE_ENABLE') ?: 'false'), FILTER_VALIDATE_BOOLEAN),
        'url' => (string) (getenv('RESCUE_URL') ?: 'https://api.ismart.pro/v1/rescue'),
        'site' => (string) (getenv('RESCUE_SITE') ?: ''),
        'key' => (string) (getenv('RESCUE_KEY') ?: ''),
        'timeout' => (int) (getenv('RESCUE_TIMEOUT') ?: 10),
    ],
    'calltouch' => [
        'enable' => filter_var((string) (getenv('CALLTOUCH_ENABLE') ?: 'false'), FILTER_VALIDATE_BOOLEAN),
        'route_key' => (string) (getenv('CALLTOUCH_ROUTE_KEY') ?: ''),
        'token' => (string) (getenv('CALLTOUCH_TOKEN') ?: ''),
        'timeout' => (int) (getenv('CALLTOUCH_TIMEOUT') ?: 10),
    ],
    'telegram' => [
        'enable' => filter_var((string) (getenv('TELEGRAM_ENABLE') ?: 'false'), FILTER_VALIDATE_BOOLEAN),
        'bot_token' => (string) (getenv('TELEGRAM_BOT_TOKEN') ?: ''),
        'chat_id' => (string) (getenv('TELEGRAM_CHAT_ID') ?: ''),
        'timeout' => (int) (getenv('TELEGRAM_TIMEOUT') ?: 10),
    ],
    'google_sheets' => [
        'enable' => filter_var((string) (getenv('SHEETS_ENABLE') ?: 'false'), FILTER_VALIDATE_BOOLEAN),
        'spreadsheet_id' => (string) (getenv('SHEETS_SPREADSHEET_ID') ?: ''),
        'sheet_name' => (string) (getenv('SHEETS_SHEET_NAME') ?: 'Заявки'),
        'credentials_path' => (string) (getenv('SHEETS_CREDENTIALS_PATH') ?: 'config/secrets/google-service-account.json'),
        'timeout' => (int) (getenv('SHEETS_TIMEOUT') ?: 10),
    ],
    'errors' => require __DIR__ . '/errors.php',
    'twig' => [
        'cache' => $isProduction ? $cacheDir . '/twig' : false,
        'debug' => $isDebug,
        'auto_reload' => !$isProduction,
    ],
    'paths' => [
        'templates' => $projectRoot . '/templates',
        'json_base' => $projectRoot . '/data/json',
        'json_global' => $projectRoot . '/data/json/global.json',
        'json_pages_dir' => $projectRoot . '/data/json/{lang}/pages',
        'redirects' => $projectRoot . '/config/redirects.json',
        'cache' => $cacheDir,
        'logs' => $projectRoot . '/logs',
    ],
    'image_sizes' => $image_sizes,
    'resource_hints' => [
        ['rel' => 'preconnect', 'href' => 'https://mc.yandex.ru', 'crossorigin' => false],
        ['rel' => 'preconnect', 'href' => 'https://yastatic.net', 'crossorigin' => false],
    ],
];
