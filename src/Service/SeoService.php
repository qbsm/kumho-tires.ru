<?php

namespace App\Service;

use Twig\Environment;
use Twig\Error\Error;

final class SeoService
{
    public function processTemplates(?array $seoData, array $context, Environment $twig): ?array
    {
        if ($seoData === null) {
            return null;
        }

        try {
            $render = function ($value) use (&$render, $context, $twig) {
                if (is_array($value)) {
                    $result = [];
                    foreach ($value as $key => $item) {
                        $result[$key] = $render($item);
                    }
                    return $result;
                }

                if (is_string($value)) {
                    return $twig->createTemplate($value)->render($context);
                }

                return $value;
            };

            return $render($seoData);
        } catch (Error $e) {
            error_log('SEO Twig error: ' . $e->getMessage());
            return $seoData;
        }
    }
}
