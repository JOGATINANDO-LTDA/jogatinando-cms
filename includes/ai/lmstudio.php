<?php
/**
 * LM Studio — Local AI provider (http://localhost:1234).
 * OpenAI-compatible endpoint at /v1/chat/completions.
 */
require_once __DIR__ . '/provider.php';

class LMStudioProvider extends AIProvider {
    public function __construct(array $config = []) {
        parent::__construct(array_merge([
            'slug' => 'lmstudio',
            'name' => 'LM Studio (Local)',
            'base_url' => 'http://localhost:1234/v1',
            'auth_type' => 'bearer',
            'models_endpoint' => '/v1/models',
            'chat_endpoint' => '/v1/chat/completions',
            'supports_streaming' => 1,
        ], $config));
    }

    public function listModels(string $apiKey): array {
        try {
            $headers = ['Content-Type: application/json'];
            $url = $this->baseUrl . $this->modelsEndpoint;
            $data = $this->request('GET', $url, $headers, null, 5);

            if (!empty($data['data']) && is_array($data['data'])) {
                return array_map(fn($m) => [
                    'id' => $m['id'],
                    'name' => $m['name'] ?? $m['id'],
                ], $data['data']);
            }
        } catch (Exception $e) {
            // LM Studio not running
        }

        return [];
    }

    public function chatCompletion(string $apiKey, array $messages, array $options = []): array {
        $headers = ['Content-Type: application/json'];

        $payload = [
            'model' => $options['model'] ?? '',
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        $url = $this->baseUrl . $this->chatEndpoint;
        $data = $this->request('POST', $url, $headers, json_encode($payload), $options['timeout'] ?? 120);

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0],
            'model' => $data['model'] ?? $payload['model'],
        ];
    }
}
