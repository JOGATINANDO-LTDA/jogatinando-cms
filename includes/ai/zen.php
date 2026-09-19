<?php
/**
 * OpenCode Zen — Default AI provider with 7 free models.
 * API: https://opencode.ai/zen/v1 (OpenAI-compatible)
 */
require_once __DIR__ . '/provider.php';

class ZenProvider extends AIProvider {
    private const FREE_MODELS = [
        ['id' => 'mimo-v2.5-free', 'name' => 'Mimo v2.5 (Free)'],
        ['id' => 'mimo-v2-flash', 'name' => 'Mimo v2 Flash (Free)'],
        ['id' => 'mimo-v2-turbo', 'name' => 'Mimo v2 Turbo (Free)'],
        ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash (Free)'],
        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Free)'],
        ['id' => 'deepseek-r1-0528', 'name' => 'DeepSeek R1 (Free)'],
        ['id' => 'qwen3-235b-a22b', 'name' => 'Qwen3 235B (Free)'],
    ];

    public function listModels(string $apiKey): array {
        // Try live endpoint first
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
            // Fallback to static list
        }

        return self::FREE_MODELS;
    }

    public function chatCompletion(string $apiKey, array $messages, array $options = []): array {
        $headers = [
            'Content-Type: application/json',
            $this->authHeader($apiKey),
        ];

        $payload = [
            'model' => $options['model'] ?? 'mimo-v2.5-free',
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if (!empty($options['stream'])) {
            $payload['stream'] = true;
        }

        $url = $this->baseUrl . $this->chatEndpoint;
        $data = $this->request('POST', $url, $headers, json_encode($payload), $options['timeout'] ?? 60);

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? ['prompt_tokens' => 0, 'completion_tokens' => 0],
            'model' => $data['model'] ?? $payload['model'],
        ];
    }
}
