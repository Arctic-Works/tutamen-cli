<?php

declare(strict_types=1);

namespace Tutamen\Cli\Config;

use RuntimeException;

/**
 * The CLI's credential store: server URL + API token, kept in
 * ~/.config/tutamen/config.json with 0600 permissions. NEVER committed —
 * .tutamen.json is the committed, secret-free config; this is the secret one.
 */
final class Config
{
    /**
     * @param  string  $baseDir  the ~/.config/tutamen directory (overridable for tests)
     */
    public function __construct(private readonly string $baseDir)
    {
    }

    /**
     * Resolve the config directory from the environment: TUTAMEN_CONFIG_HOME
     * wins (tests, CI), then XDG_CONFIG_HOME/tutamen, then ~/.config/tutamen.
     */
    public static function fromEnvironment(): self
    {
        $explicit = getenv('TUTAMEN_CONFIG_HOME');
        if (is_string($explicit) && $explicit !== '') {
            return new self($explicit);
        }

        $xdg = getenv('XDG_CONFIG_HOME');
        if (is_string($xdg) && $xdg !== '') {
            return new self(rtrim($xdg, '/').'/tutamen');
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: getcwd();

        return new self(rtrim((string) $home, '/').'/.config/tutamen');
    }

    public function path(): string
    {
        return $this->baseDir.'/config.json';
    }

    /**
     * Where the agent prompt is cached (alongside the credentials, same dir).
     */
    public function promptCachePath(): string
    {
        return $this->baseDir.'/agent-prompt.json';
    }

    /**
     * @return array{server?: string, token?: string}
     */
    public function load(): array
    {
        if (! is_file($this->path())) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path()), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function save(string $server, string $token): void
    {
        if (! is_dir($this->baseDir) && ! @mkdir($this->baseDir, 0700, true) && ! is_dir($this->baseDir)) {
            throw new RuntimeException("Could not create config directory: {$this->baseDir}");
        }

        $json = json_encode(['server' => $server, 'token' => $token], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->path(), $json.PHP_EOL);
        @chmod($this->path(), 0600);
    }

    public function clear(): bool
    {
        if (is_file($this->path())) {
            return unlink($this->path());
        }

        return false;
    }

    public function isAuthenticated(): bool
    {
        $config = $this->load();

        return ! empty($config['server']) && ! empty($config['token']);
    }
}
