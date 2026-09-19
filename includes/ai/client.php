<?php
/**
 * AI Client — Unified interface for all AI providers.
 * Loads provider config from DB, routes requests, tracks usage.
 */
require_once __DIR__ . '/provider.php';
require_once __DIR__ . '/zen.php';
require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/lmstudio.php';
require_once __DIR__ . '/openai-compat.php';

class AIClient {
    private static ?AIClient $instance = null;
    private PDO $db;
    private ?AIProvider $provider = null;
    private array $config = [];

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db = getDB();
        $this->loadDefaultConfig();
    }

    /**
     * Load the default AI config from DB.
     */
    private function loadDefaultConfig(): void {
        try {
            // Get default provider config (is_default=1 or first active)
            $row = $this->db->query("
                SELECT c.*, p.slug, p.name, p.base_url, p.auth_type, p.models_endpoint, p.chat_endpoint, p.supports_streaming
                FROM ai_configs c
                JOIN ai_providers p ON c.provider_id = p.id
                WHERE p.active = 1 AND c.is_default = 1
                ORDER BY c.id
                LIMIT 1
            ")->fetch();

            if (!$row) {
                // Fallback: any active provider config
                $row = $this->db->query("
                    SELECT c.*, p.slug, p.name, p.base_url, p.auth_type, p.models_endpoint, p.chat_endpoint, p.supports_streaming
                    FROM ai_configs c
                    JOIN ai_providers p ON c.provider_id = p.id
                    WHERE p.active = 1
                    ORDER BY c.is_default DESC, c.id
                    LIMIT 1
                ")->fetch();
            }

            if ($row) {
                $this->config = $row;
                $this->provider = $this->createProvider($row);
            }
        } catch (Exception $e) {
            // Tables may not exist yet
        }
    }

    /**
     * Create a provider instance from DB row.
     */
    private function createProvider(array $row): AIProvider {
        $config = [
            'slug' => $row['slug'],
            'name' => $row['name'],
            'base_url' => $row['base_url'],
            'auth_type' => $row['auth_type'],
            'models_endpoint' => $row['models_endpoint'],
            'chat_endpoint' => $row['chat_endpoint'],
            'supports_streaming' => $row['supports_streaming'],
        ];

        return match ($row['slug']) {
            'zen' => new ZenProvider($config),
            'ollama' => new OllamaProvider($config),
            'lmstudio' => new LMStudioProvider($config),
            default => new OpenAICompatProvider($config),
        };
    }

    /**
     * Switch to a specific provider config by ID.
     */
    public function switchConfig(int $configId): void {
        $row = $this->db->prepare("
            SELECT c.*, p.slug, p.name, p.base_url, p.auth_type, p.models_endpoint, p.chat_endpoint, p.supports_streaming
            FROM ai_configs c
            JOIN ai_providers p ON c.provider_id = p.id
            WHERE c.id = ? AND p.active = 1
        ");
        $row->execute([$configId]);
        $row = $row->fetch();

        if ($row) {
            $this->config = $row;
            $this->provider = $this->createProvider($row);
        }
    }

    /**
     * Check if AI is configured and available.
     */
    public function isAvailable(): bool {
        return $this->provider !== null;
    }

    /**
     * Get current provider name.
     */
    public function getProviderName(): string {
        return $this->provider?->getName() ?? 'Nenhum';
    }

    /**
     * Get current model.
     */
    public function getModel(): string {
        return $this->config['model_slug'] ?? 'N/A';
    }

    /**
     * List models from current provider.
     */
    public function listModels(): array {
        if (!$this->provider) return [];
        return $this->provider->listModels($this->config['api_key'] ?? '');
    }

    /**
     * Send a chat completion request.
     * Logs usage to ai_usage table.
     */
    public function chat(array $messages, array $options = []): array {
        if (!$this->provider) {
            throw new RuntimeException('Nenhum provider de IA configurado');
        }

        $mergedOptions = [
            'model' => $this->config['model_slug'] ?? null,
            'max_tokens' => $this->config['max_tokens'] ?? 4096,
            'temperature' => $this->config['temperature'] ?? 0.7,
        ] + $options;

        $start = microtime(true);
        $result = $this->provider->chatCompletion($this->config['api_key'] ?? '', $messages, $mergedOptions);
        $latencyMs = (int)((microtime(true) - $start) * 1000);

        // Log usage
        $this->logUsage($result['usage'] ?? [], $latencyMs, $options['feature'] ?? 'chat');

        return $result;
    }

    /**
     * Quick helper: send a single prompt and get text response.
     */
    public function ask(string $prompt, array $options = []): string {
        $messages = [['role' => 'user', 'content' => $prompt]];

        if (!empty($this->config['system_prompt'])) {
            array_unshift($messages, ['role' => 'system', 'content' => $this->config['system_prompt']]);
        }

        $result = $this->chat($messages, $options);
        return $result['content'];
    }

    /**
     * Log usage to ai_usage table.
     */
    private function logUsage(array $usage, int $latencyMs, string $feature): void {
        try {
            $costCents = $this->estimateCost($usage);
            $stmt = $this->db->prepare("
                INSERT INTO ai_usage (config_id, feature, prompt_tokens, completion_tokens, latency_ms, cost_cents)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->config['id'] ?? 0,
                $feature,
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0,
                $latencyMs,
                $costCents,
            ]);
        } catch (Exception $e) {
            // Non-critical, ignore
        }
    }

    /**
     * Estimate cost in cents based on provider/model heuristic.
     * Uses approximate per-1K-token rates; actual cost varies by provider.
     */
    private function estimateCost(array $usage): int {
        $promptTokens = (int)($usage['prompt_tokens'] ?? 0);
        $completionTokens = (int)($usage['completion_tokens'] ?? 0);

        $providerSlug = $this->config['slug'] ?? '';
        $modelSlug = $this->config['model_slug'] ?? '';

        // Approximate $/1K tokens (prompt, completion)
        $rates = [
            'zen' => [0.5, 2.0],       // mimo-v2.5-free is free tier — nominal
            'ollama' => [0, 0],         // Local — no cost
            'lmstudio' => [0, 0],       // Local — no cost
        ];

        $rate = $rates[$providerSlug] ?? [3.0, 6.0]; // Default: GPT-4o-like rates

        // For openai-compatible, check model slug
        if ($providerSlug === 'openai-compat' || !isset($rates[$providerSlug])) {
            if (str_contains($modelSlug, 'gpt-4')) {
                $rate = [3.0, 6.0];
            } elseif (str_contains($modelSlug, 'gpt-3.5')) {
                $rate = [0.5, 1.5];
            } elseif (str_contains($modelSlug, 'llama')) {
                $rate = [0.2, 0.6];
            }
        }

        $promptCost = ($promptTokens / 1000) * $rate[0];
        $completionCost = ($completionTokens / 1000) * $rate[1];

        return (int)(($promptCost + $completionCost) * 100); // Convert to cents
    }
}
