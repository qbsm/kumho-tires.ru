<?php

declare(strict_types=1);

namespace App\Service;

/**
 * SEO-builder для коллекции шин: усиленный title + Product JSON-LD (Schema.org).
 *
 * Title и Product-разметка строятся из данных модели (item.name/code, season, sizes).
 * Цены/рейтинги НЕ выдумываются — Offer/AggregateRating добавляются только при наличии реальных данных.
 */
final class TireSeoBuilder implements SeoBuilderInterface
{
    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $itemKey = (string) ($config['item_key'] ?? 'item');
        $inner = $itemKey !== '' ? (array) ($entity[$itemKey] ?? []) : $entity;

        $name = (string) ($inner['name'] ?? $inner['title'] ?? $entity['slug'] ?? '');
        $code = (string) ($inner['code'] ?? '');
        $season = (string) ($entity['season'] ?? '');
        $descShort = (string) ($entity['desc']['short'] ?? '');
        $descFull = (string) ($entity['desc']['full'] ?? '');
        $desc = $descShort !== '' ? $descShort : $descFull;
        $siteName = (string) ($global['name'] ?? $global['site_name'] ?? '');
        $origin = rtrim($baseUrl, '/');

        // Диапазон посадочных диаметров из таблицы размеров (для title и разметки).
        $diameters = [];
        foreach ((array) ($entity['sizes'] ?? []) as $size) {
            if (is_array($size) && isset($size['diameter']) && (int) $size['diameter'] > 0) {
                $diameters[] = (int) $size['diameter'];
            }
        }
        $sizeStr = '';
        if ($diameters !== []) {
            $min = min($diameters);
            $max = max($diameters);
            $sizeStr = $min === $max ? ('R' . $min) : ('R' . $min . '–R' . $max);
        }

        // Усиленный SEO-title (на h1 не влияет — он в шаблоне).
        $titleParts = [];
        if (stripos($name, 'шина') === false) {
            $titleParts[] = 'Шина';
        }
        $titleParts[] = $name;
        if ($sizeStr !== '') {
            $titleParts[] = $sizeStr;
        }
        $title = trim(implode(' ', $titleParts));
        if ($title !== '') {
            // Длинные названия моделей вместе с полным хвостом вылезают за 65 символов и
            // обрезаются в выдаче — для них берём короткий хвост.
            $suffix = ' — характеристики и размеры';
            if (mb_strlen($title . $suffix) > 65) {
                $suffix = ' — характеристики';
            }
            $title .= $suffix;
        } else {
            $title = $name;
        }

        $imgSrc = '';
        foreach (['30deg', 'front', 'side', 'back'] as $k) {
            if (isset($entity['images'][$k]['src']) && is_string($entity['images'][$k]['src'])) {
                $imgSrc = (string) $entity['images'][$k]['src'];
                break;
            }
        }
        // Путь может прийти уже абсолютным (loadJson абсолютизирует) — домен второй раз не клеим.
        $imageUrl = '';
        if ($imgSrc !== '') {
            $imageUrl = preg_match('~^https?://~i', $imgSrc) === 1 ? $imgSrc : $origin . '/' . ltrim($imgSrc, '/');
        }

        // og:image — рендер конкретной модели, а не общая картинка сайта: в выдаче и репостах
        // каждая шина выглядит собой. Для превью нужен JPEG-двойник <имя>-og.jpg (1200×630,
        // генерируется рядом с raw-рендером): Telegram/WhatsApp не принимают webp.
        $ogImage = $origin . '/data/img/seo/og.jpg?v=3';
        if ($imageUrl !== '') {
            $ogImage = str_ends_with($imageUrl, '.webp')
                ? substr($imageUrl, 0, -5) . '-og.jpg'
                : $imageUrl;
        }
        $slug = (string) ($entity['slug'] ?? '');
        $navSlug = (string) ($config['nav_slug'] ?? 'tires');
        $pageUrl = $slug !== '' ? $origin . '/' . $navSlug . '/' . $slug : '';

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'website')],
            ['property' => 'og:title', 'content' => $title],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:image', 'content' => $ogImage],
        ];
        if ($pageUrl !== '') {
            $meta[] = ['property' => 'og:url', 'content' => $pageUrl];
        }
        if ($siteName !== '') {
            $meta[] = ['property' => 'og:site_name', 'content' => $siteName];
        }

        // Product JSON-LD. Kumho — бренд шин («Кумхо» по-русски), Kumho Tire — компания-производитель.
        $product = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $name,
            'description' => $desc,
            'brand' => ['@type' => 'Brand', 'name' => 'Kumho', 'alternateName' => 'Кумхо'],
            'manufacturer' => ['@type' => 'Organization', 'name' => 'Kumho Tire'],
            'category' => 'Автомобильные шины',
        ];
        if ($code !== '') {
            $product['mpn'] = $code;
        }
        // Синонимы названия (кириллица, форма «Plus») — по ним модель ищут чаще, чем по написанию из каталога.
        $altNames = array_values(array_filter(
            (array) ($inner['alt_names'] ?? []),
            static fn($v): bool => is_string($v) && $v !== ''
        ));
        if ($altNames !== []) {
            $product['alternateName'] = $altNames;
        }
        if ($pageUrl !== '') {
            $product['url'] = $pageUrl;
        }
        if ($imageUrl !== '') {
            $product['image'] = $imageUrl;
        }
        $props = [];
        if ($season !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Сезонность', 'value' => $season];
        }
        if ($sizeStr !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Посадочный диаметр', 'value' => $sizeStr];
        }
        // Полный перечень типоразмеров и тип транспорта — машиночитаемые факты для поисковых
        // ассистентов: по ним отвечают на «есть ли 205/55 R16» и «подойдёт ли на кроссовер».
        $labels = [];
        foreach ((array) ($entity['sizes'] ?? []) as $size) {
            if (is_array($size) && isset($size['label']) && is_string($size['label']) && $size['label'] !== '') {
                $labels[] = $size['label'];
            }
        }
        if ($labels !== []) {
            $props[] = [
                '@type' => 'PropertyValue',
                'name' => 'Типоразмеры',
                'value' => implode(', ', array_values(array_unique($labels))),
            ];
        }
        $vehicleLabels = [
            'passenger' => 'легковые автомобили',
            'suv' => 'кроссоверы и внедорожники',
            'commercial' => 'коммерческий транспорт',
        ];
        $vehicles = [];
        foreach ((array) ($entity['filter']['vehicle'] ?? []) as $vehicle) {
            if (is_string($vehicle) && isset($vehicleLabels[$vehicle])) {
                $vehicles[] = $vehicleLabels[$vehicle];
            }
        }
        if ($vehicles !== []) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Тип транспорта', 'value' => implode(', ', $vehicles)];
        }
        // Евромаркировка: классы привязаны к типоразмеру, поэтому у модели их корректно
        // показывать только диапазоном по зарегистрированным размерам.
        $eu = $this->euSummary($entity);
        if ($eu !== null) {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Класс топливной экономичности', 'value' => $eu['energy']];
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Класс сцепления на мокрой дороге', 'value' => $eu['wet']];
            if ($eu['noise'] !== '') {
                $props[] = ['@type' => 'PropertyValue', 'name' => 'Внешний шум качения', 'value' => $eu['noise']];
            }
            if ($eu['snow']) {
                $props[] = ['@type' => 'PropertyValue', 'name' => 'Маркировка 3PMSF', 'value' => 'да'];
            }
            if ($eu['ice']) {
                $props[] = ['@type' => 'PropertyValue', 'name' => 'Маркировка ice grip', 'value' => 'да'];
            }
        }
        if ($props !== []) {
            $product['additionalProperty'] = $props;
        }

        $jsonLd = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // FAQPage-разметку отдаёт компонент accordion по видимому блоку — второй JSON-LD был бы дублем.
        return [
            'title' => $title,
            'meta' => $meta,
            'json_ld' => $jsonLd !== false ? $jsonLd : null,
            'json_ld_faq' => null,
            'faq_items' => $this->buildFaq($entity, $name, $season, $sizeStr, $vehicles, $altNames),
        ];
    }

    /**
     * Вопросы-ответы собираются только из данных модели: выдуманных характеристик в FAQ быть не должно.
     *
     * @param array<string,mixed> $entity
     * @param array<int,string> $vehicles
     * @param array<int,string> $altNames
     * @return array<int,array{q:string,a:string}>
     */
    private function buildFaq(
        array $entity,
        string $name,
        string $season,
        string $sizeStr,
        array $vehicles,
        array $altNames
    ): array {
        if ($name === '') {
            return [];
        }

        $faq = [];

        $count = count((array) ($entity['sizes'] ?? []));
        if ($count > 0) {
            $parts = [];
            if ($sizeStr !== '') {
                $parts[] = str_contains($sizeStr, '–')
                    ? 'посадочные диаметры ' . $sizeStr
                    : 'посадочный диаметр ' . $sizeStr;
            }
            $widths = $this->minMax((array) ($entity['filter']['widths'] ?? []));
            if ($widths !== null) {
                $parts[] = $widths[0] === $widths[1]
                    ? 'ширина профиля ' . $widths[0] . ' мм'
                    : 'ширина профиля от ' . $widths[0] . ' до ' . $widths[1] . ' мм';
            }
            $profiles = $this->minMax((array) ($entity['filter']['profiles'] ?? []));
            if ($profiles !== null) {
                $parts[] = $profiles[0] === $profiles[1]
                    ? 'высота профиля ' . $profiles[0] . '%'
                    : 'высота профиля от ' . $profiles[0] . ' до ' . $profiles[1] . '%';
            }
            $answer = 'Модель выпускается в ' . $count . ' '
                . $this->plural($count, 'типоразмере', 'типоразмерах', 'типоразмерах');
            if ($parts !== []) {
                $answer .= ': ' . implode(', ', $parts);
            }
            $answer .= '. Полный перечень с индексами нагрузки и скорости — в таблице «Доступные размеры» на этой странице.';
            $faq[] = ['q' => 'Какие типоразмеры есть у ' . $name . '?', 'a' => $answer];
        }

        if ($season !== '' || $vehicles !== []) {
            $answer = '';
            if ($season !== '') {
                $answer .= 'Сезонность — ' . mb_strtolower($season) . '.';
            }
            if ($vehicles !== []) {
                $answer .= ($answer !== '' ? ' ' : '') . 'Модель рассчитана на ' . implode(', ', $vehicles) . '.';
            }
            $faq[] = ['q' => 'Для каких автомобилей и условий подходит ' . $name . '?', 'a' => $answer];
        }

        $eu = $this->euSummary($entity);
        if ($eu !== null) {
            $answer = 'Класс топливной экономичности — ' . $eu['energy']
                . ', класс сцепления на мокрой дороге — ' . $eu['wet'];
            if ($eu['noise'] !== '') {
                $answer .= ', внешний шум качения — ' . $eu['noise'];
            }
            $answer .= '.';
            if ($eu['snow']) {
                $answer .= ' Подтверждена маркировка 3PMSF (три горных пика со снежинкой)'
                    . ($eu['ice'] ? ' и ice grip.' : '.');
            }
            $answer .= ' Значения приведены по ' . $eu['covered'] . ' из ' . $eu['total'] . ' '
                . $this->plural($eu['total'], 'типоразмера', 'типоразмеров', 'типоразмеров')
                . ', зарегистрированным в реестре ЕС EPREL: у разных размеров классы отличаются.';
            $faq[] = ['q' => 'Какая евромаркировка у ' . $name . '?', 'a' => $answer];
        }

        $cyrillic = null;
        $latinAlt = null;
        foreach ($altNames as $alt) {
            if ($cyrillic === null && preg_match('/[А-Яа-яЁё]/u', $alt) === 1) {
                $cyrillic = $alt;
            } elseif ($latinAlt === null && preg_match('/[А-Яа-яЁё]/u', $alt) === 0) {
                $latinAlt = $alt;
            }
        }
        if ($cyrillic !== null) {
            $answer = 'По-русски — «' . $cyrillic . '», латиницей — ' . $name . '.';
            if ($latinAlt !== null) {
                $answer .= ' Встречается также написание ' . $latinAlt . '.';
            }
            $faq[] = ['q' => 'Как пишется ' . $name . ' по-русски?', 'a' => $answer];
        }

        $faq[] = [
            'q' => 'Где купить ' . $name . '?',
            'a' => 'Шины продаются в авторизованных шинных центрах в городах России — адреса и контакты в разделе «Где купить».',
        ];

        return $faq;
    }

    /**
     * Сводка евромаркировки по типоразмерам: классы регистрируются на каждый размер отдельно,
     * поэтому у модели честен только диапазон и число охваченных размеров.
     *
     * @param array<string,mixed> $entity
     * @return array{energy:string,wet:string,noise:string,snow:bool,ice:bool,covered:int,total:int}|null
     */
    private function euSummary(array $entity): ?array
    {
        $sizes = (array) ($entity['sizes'] ?? []);
        $energy = [];
        $wet = [];
        $noise = [];
        $snow = false;
        $ice = false;
        $covered = 0;

        foreach ($sizes as $size) {
            $eu = is_array($size) ? ($size['eu'] ?? null) : null;
            if (!is_array($eu)) {
                continue;
            }
            $covered++;
            if (is_string($eu['energy'] ?? null)) {
                $energy[$eu['energy']] = true;
            }
            if (is_string($eu['wet'] ?? null)) {
                $wet[$eu['wet']] = true;
            }
            if (is_numeric($eu['noise'] ?? null)) {
                $noise[] = (int) $eu['noise'];
            }
            $snow = $snow || ($eu['snow'] ?? false) === true;
            $ice = $ice || ($eu['ice'] ?? false) === true;
        }

        if ($covered === 0 || $energy === [] || $wet === []) {
            return null;
        }

        $range = static function (array $classes): string {
            $keys = array_keys($classes);
            sort($keys);

            return count($keys) === 1 ? $keys[0] : $keys[0] . '–' . end($keys);
        };

        $noiseStr = '';
        if ($noise !== []) {
            $noiseStr = min($noise) === max($noise)
                ? min($noise) . ' дБ'
                : min($noise) . '–' . max($noise) . ' дБ';
        }

        return [
            'energy' => $range($energy),
            'wet' => $range($wet),
            'noise' => $noiseStr,
            'snow' => $snow,
            'ice' => $ice,
            'covered' => $covered,
            'total' => count($sizes),
        ];
    }

    /**
     * @param array<int,mixed> $values
     * @return array{0:int,1:int}|null
     */
    private function minMax(array $values): ?array
    {
        $ints = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ints[] = (int) $value;
            }
        }

        return $ints === [] ? null : [min($ints), max($ints)];
    }

    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod100 = $count % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        return match ($count % 10) {
            1 => $one,
            2, 3, 4 => $few,
            default => $many,
        };
    }
}
