<?php
/**
 * AI Provider — Base class for all providers.
 * Each provider implements chat completion via OpenAI-compatible API.
 */
abstract class AIProvider {
    protected string $slug;
    protected string $name;
    protected string $baseUrl;
    protected string $authType;
    protected string $modelsEndpoint;
    protected string $chatEndpoint;
    protected bool $supportsStreaming;

    public function __construct(array $config) {
        $this->slug = $config['slug'] ?? '';
        $this->name = $config['name'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->authType = $config['auth_type'] ?? 'bearer';
        $this->modelsEndpoint = $config['models_endpoint'] ?? '/v1/models';
        $this->chatEndpoint = $config['chat_endpoint'] ?? '/v1/chat/completions';
        $this->supportsStreaming = (bool)($config['supports_streaming'] ?? 1);
    }

    public function getSlug(): string { return $this->slug; }
    public function getName(): string { return $this->name; }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function supportsStreaming(): bool { return $this->supportsStreaming; }

    /**
     * Build Authorization header value.
     */
    protected function authHeader(string $apiKey): string {
        return match ($this->authType) {
            'bearer' => 'Bearer ' . $apiKey,
            'basic' => 'Basic ' . base64_encode($apiKey . ':'),
            default => $apiKey,
        };
    }

    /**
     * List available models from the provider.
     * Returns array of ['id' => ..., 'name' => ...].
     */
    abstract public function listModels(string $apiKey): array;

    /**
     * Send a chat completion request.
     * Returns ['content' => ..., 'usage' => [...], 'model' => ...].
     */
    abstract public function chatCompletion(string $apiKey, array $messages, array $options = []): array;

    /**
     * Common cURL helper with timeout and error handling.
     */
    protected function request(string $method, string $url, array $headers, ?string $body = null, int $timeout = 30): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("cURL error: {$error}");
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? $data['error'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Provider error: {$msg}");
        }

        return $data ?? [];
    }
}
