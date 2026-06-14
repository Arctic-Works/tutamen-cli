<?php

declare(strict_types=1);

namespace Tutamen\Cli\Config;

use RuntimeException;

/**
 * The committed, shared, SECRET-FREE project config: `.tutamen.json` at the
 * repo root. Carries hook settings (branch regex, severity threshold) the
 * whole team inherits. It must never hold a token — if one is found, the CLI
 * refuses to read it rather than risk a committed credential.
 */
final class ProjectConfig
{
    public const FILENAME = '.tutamen.json';

    // Token-shaped content we refuse to read from a committed file: a legacy
    // Sanctum "{id}|{40+}" plaintext, or any long value under a secret-ish key.
    private const TOKEN_PATTERNS = [
        '/\d+\|[A-Za-z0-9]{40,}/',
        '/"(?:token|secret|api[_-]?key|password)"\s*:\s*"[^"]{20,}"/i',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private readonly array $data)
    {
    }

    public static function load(string $repoRoot): self
    {
        $path = rtrim($repoRoot, '/').'/'.self::FILENAME;

        if (! is_file($path)) {
            return new self([]);
        }

        $raw = (string) file_get_contents($path);

        foreach (self::TOKEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $raw) === 1) {
                throw new RuntimeException(
                    self::FILENAME.' appears to contain an API token or secret. This file is committed and must never hold secrets — '
                    .'store your token with `tutamen auth` instead and remove it from '.self::FILENAME.'.'
                );
            }
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(self::FILENAME.' is not valid JSON.');
        }

        return new self($decoded);
    }

    /**
     * The configured severity threshold: hooks.failOn first (shared hook
     * setting), then a top-level failOn, else null so the caller's default
     * applies. Always: an explicit CLI flag overrides this.
     */
    public function failOn(): ?string
    {
        $hookFailOn = $this->data['hooks']['failOn'] ?? null;

        if (is_string($hookFailOn) && $hookFailOn !== '') {
            return $hookFailOn;
        }

        $failOn = $this->data['failOn'] ?? null;

        return is_string($failOn) && $failOn !== '' ? $failOn : null;
    }

    /**
     * The branch regex the pre-push hook gates on (only matching branches are
     * scanned). Null means "every branch".
     */
    public function hookBranches(): ?string
    {
        $branches = $this->data['hooks']['branches'] ?? null;

        return is_string($branches) && $branches !== '' ? $branches : null;
    }

    /**
     * Persist hook settings into the committed .tutamen.json so the team shares
     * them. Only non-null values are written; existing keys are preserved.
     */
    public static function writeHookSettings(string $repoRoot, ?string $branches, ?string $failOn): void
    {
        $config = self::load($repoRoot); // also enforces the no-secrets guard
        $data = $config->data;

        $hooks = is_array($data['hooks'] ?? null) ? $data['hooks'] : [];

        if ($branches !== null) {
            $hooks['branches'] = $branches;
        }

        if ($failOn !== null) {
            $hooks['failOn'] = $failOn;
        }

        $data['hooks'] = $hooks;

        $path = rtrim($repoRoot, '/').'/'.self::FILENAME;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
