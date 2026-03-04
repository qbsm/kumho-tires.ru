<?php

declare(strict_types=1);

namespace App\Action;

use App\Api\PhotoroomApiClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class PhotoroomRemoveBackgroundAction
{
    public function __construct(
        private readonly PhotoroomApiClient $photoroomApiClient,
        private readonly LoggerInterface $logger,
        /** @var array<string,mixed> */
        private readonly array $settings
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $internalToken = (string) ($this->settings['photoroom']['internal_token'] ?? '');
        if ($internalToken !== '') {
            $providedToken = (string) $request->getHeaderLine('X-Internal-Token');
            if (!hash_equals($internalToken, $providedToken)) {
                return $this->json($response, 403, [
                    'success' => false,
                    'code' => 'FORBIDDEN',
                    'message' => 'Недостаточно прав для выполнения операции.',
                ]);
            }
        }

        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['image'] ?? $uploadedFiles['image_file'] ?? null;
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, 422, [
                'success' => false,
                'code' => 'IMAGE_REQUIRED',
                'message' => 'Передайте изображение в поле image или image_file.',
            ]);
        }

        $parsed = $request->getParsedBody();
        $data = is_array($parsed) ? $parsed : [];
        $format = isset($data['format']) && is_string($data['format']) ? strtolower(trim($data['format'])) : 'png';
        if (!in_array($format, ['png', 'webp', 'jpg', 'jpeg'], true)) {
            return $this->json($response, 422, [
                'success' => false,
                'code' => 'FORMAT_INVALID',
                'message' => 'Поддерживаются форматы: png, webp, jpg, jpeg.',
            ]);
        }

        $outputSize = isset($data['output_size']) && is_string($data['output_size']) ? trim($data['output_size']) : '';
        if ($outputSize !== '' && !preg_match('/^\d+x\d+$/', $outputSize)) {
            return $this->json($response, 422, [
                'success' => false,
                'code' => 'OUTPUT_SIZE_INVALID',
                'message' => 'output_size должен быть в формате WIDTHxHEIGHT, например 1280x1280.',
            ]);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'photoroom_');
        if ($tempPath === false) {
            return $this->json($response, 500, [
                'success' => false,
                'code' => 'TMP_CREATE_FAILED',
                'message' => 'Не удалось создать временный файл.',
            ]);
        }

        try {
            $stream = $file->getStream();
            $stream->rewind();
            $contents = $stream->getContents();
            file_put_contents($tempPath, $contents);

            $result = $this->photoroomApiClient->editImage($tempPath, [
                'format' => $format,
                'removeBackground' => true,
                'backgroundColor' => 'transparent',
                'scaling' => 'fit',
                'outputSize' => $outputSize !== '' ? $outputSize : null,
            ]);

            $response->getBody()->write($result['body']);
            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', $result['contentType'])
                ->withHeader('Cache-Control', 'no-store');
        } catch (RuntimeException $e) {
            $this->logger->error('Photoroom remove background failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->json($response, 502, [
                'success' => false,
                'code' => 'PHOTOROOM_FAILED',
                'message' => 'Ошибка обработки изображения в Photoroom API.',
                'details' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
