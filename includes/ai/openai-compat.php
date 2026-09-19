<?php
/**
 * OpenAI Compatible — Generic provider for any OpenAI-compatible API.
 * Custom base URI, API key, and model required.
 */
require_once __DIR__ . '/provider.php';

class OpenAICompatProvider extends AIProvider {
    public function __construct(array $config = []) {
        parent::__construct(array_merge([
            'slug' => 'openai-compat',
            'name' => 'OpenAI Compatible',
            'base_url' => 'https://api.openai.com/v1',
            'auth_type' => 'bearer',
            'models_endpoint' => '/v1/models',
            'chat_endpoint' => '/v1/chat/completions',
            'supports_streaming' => 1,
        ], $config));
    }

    public function listModels(string $apiKey): array {
        if (empty($apiKey)) return [];

        try {
            $headers = [
                'Content-Type: application/json',
                $this->authHeader($apiKey),
            ];
            $url = $this->baseUrl . $this->modelsEndpoint;
            $data = $this->request('GET', $url, $headers, null, 10);

            if (!empty($data['data']) && is_array($data['data'])) {
                return array_map(fn($m) => [
                    'id' => $m['id'],
                    'name' => $m['name'] ?? $m['id'],
                ], $data['data']);
            }
        } catch (Exception $e) {
            // API unreachable or invalid key
        }

        return [];
    }

    public function chatCompletion(string $apiKey, array $messages, array $options = []): array {
        if (empty($apiKey)) {
            throw new RuntimeException('API key required for OpenAI-compatible provider');
        }

        $headers = [
            'Content-Type: application/json',
            $this->authHeader($apiKey),
        ];

        $payload = [
            'model' => $options['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        $url = $this->baseUrl . $this->chatEndpoint;
        $data = $this->request('POST', $url, $headers, json_encode($payload), $options['timeout'] ?? 60);

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0],
            'model' => $data['model'] ?? $payload['model'],
        ];
    }
}
