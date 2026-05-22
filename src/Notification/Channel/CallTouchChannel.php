<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\ChannelInterface;
use App\Notification\ChannelResult;
use Psr\Log\LoggerInterface;

final class CallTouchChannel implements ChannelInterface
{
    private const API_URL = 'https://api.calltouch.ru/widget-service/v1/api/widget-request/user-form/create';
    private const ERROR_VALIDATION_CODE = 10007;

    /**
     * @param array{
     *     enable?: bool,
     *     route_key?: string,
     *     token?: string,
     *     timeout?: int
     * } $config
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $config,
    ) {
    }

    public function name(): string
    {
        return 'calltouch';
    }

    public function isEnabled(): bool
    {
        return ($this->config['enable'] ?? false) === true
            && ($this->config['route_key'] ?? '') !== ''
            && ($this->config['token'] ?? '') !== '';
    }

    public function send(array $formData, array $uploadedFiles, string $requestId): ChannelResult
    {
        $payload = $this->buildPayload($formData);

        if ($payload['phone'] === '') {
            return ChannelResult::warning($this->name(), 'empty_phone');
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Access-Token: ' . $this->config['token'],
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => (int) ($this->config['timeout'] ?? 10),
            CURLOPT_TIMEOUT => (int) ($this->config['timeout'] ?? 10),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error('CallTouch: cURL error', [
                'request_id' => $requestId,
                'error' => $curlError,
            ]);
            return ChannelResult::failed($this->name(), $curlError);
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($httpCode === 200 && !empty($decoded['data']['widgetRequestId'])) {
            $widgetId = (string) $decoded['data']['widgetRequestId'];
            $this->logger->info('CallTouch: заявка отправлена', [
                'request_id' => $requestId,
                'widget_request_id' => $widgetId,
            ]);
            return ChannelResult::success($this->name(), ['widget_request_id' => $widgetId]);
        }

        $errorCode = $decoded['data']['apiErrorData']['errorCode'] ?? null;
        $isValidation = $errorCode === self::ERROR_VALIDATION_CODE
            || isset($decoded['data']['validationErrorData']);

        $message = (string) (
            $decoded['data']['apiErrorData']['errorMessage']
            ?? $decoded['data']['validationErrorData']['violations'][0]['message']
            ?? 'unknown_error'
        );

        $context = [
            'request_id' => $requestId,
            'http_code' => $httpCode,
            'message' => $message,
        ];

        if ($isValidation) {
            $this->logger->warning('CallTouch: validation error', $context);
            return ChannelResult::warning($this->name(), $message, ['http_code' => $httpCode]);
        }

        $this->logger->error('CallTouch: send failed', $context);
        return ChannelResult::failed($this->name(), $message, ['http_code' => $httpCode]);
    }

    /**
     * @param array<string,mixed> $formData
     * @return array<string,string>
     */
    private function buildPayload(array $formData): array
    {
        $phoneRaw = (string) ($formData['phone'] ?? '');
        $phone = preg_replace('/\D+/', '', $phoneRaw) ?? '';
        if ($phone !== '' && $phone[0] === '8') {
            $phone = '7' . substr($phone, 1);
        }

        $sessionId = (string) (
            $formData['session_id']
            ?? $formData['sessionId']
            ?? $_COOKIE['_ct_session_id']
            ?? ''
        );

        $payload = [
            'routeKey' => (string) ($this->config['route_key'] ?? ''),
            'phone' => $phone,
        ];

        if ($sessionId !== '') {
            $payload['sessionId'] = $sessionId;
        }

        $utmMap = [
            'utm_source' => 'utmSource',
            'utm_medium' => 'utmMedium',
            'utm_campaign' => 'utmCampaign',
            'utm_content' => 'utmContent',
            'utm_term' => 'utmTerm',
        ];
        foreach ($utmMap as $from => $to) {
            if (isset($formData[$from]) && is_string($formData[$from]) && $formData[$from] !== '') {
                $payload[$to] = $formData[$from];
            }
        }

        return $payload;
    }
}
