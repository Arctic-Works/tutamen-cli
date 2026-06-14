<?php

declare(strict_types=1);

namespace Tutamen\Cli\Agent;

use Closure;

/**
 * A tiny on-disk cache for the server-managed agent prompt. The prompt evolves
 * server-side (a PR + deploy), so the CLI must re-fetch periodically — but not
 * on every `tutamen scan --agent`. We keep the last fetch (per server) for a
 * short TTL next to the credentials and refetch once it goes stale.
 */
final class PromptCache
{
    private const TTL_SECONDS = 300;

    /**
     * @param  string  $path  the cache file (Config::promptCachePath())
     * @param  Closure(): int  $clock  current unix time — injectable for tests
     */
    public function __construct(
        private readonly string $path,
        private readonly Closure $clock,
    ) {
    }

    /**
     * Return the cached prompt for $server if it is still fresh, otherwise call
     * $fetch, persist the result, and return it.
     *
     * @param  Closure(): array{version: int, prompt: string}  $fetch
     * @return array{version: int, prompt: string}
     */
    public function remember(string $server, Closure $fetch): array
    {
        $now = ($this->clock)();
        $cached = $this->read();

        if ($cached !== null
            && ($cached['server'] ?? null) === $server
            && ($now - (int) ($cached['fetched_at'] ?? 0)) < self::TTL_SECONDS) {
            return ['version' => (int) $cached['version'], 'prompt' => (string) $cached['prompt']];
        }

        $fresh = $fetch();
        $this->write($server, $fresh, $now);

        return $fresh;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array{version: int, prompt: string}  $prompt
     */
    private function write(string $server, array $prompt, int $now): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return; // best-effort cache; a write failure just means we refetch
        }

        @file_put_contents($this->path, (string) json_encode([
            'server' => $server,
            'version' => $prompt['version'],
            'prompt' => $prompt['prompt'],
            'fetched_at' => $now,
        ], JSON_UNESCAPED_SLASHES));
        @chmod($this->path, 0600);
    }
}
