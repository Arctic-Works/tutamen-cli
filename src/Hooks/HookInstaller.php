<?php

declare(strict_types=1);

namespace Tutamen\Cli\Hooks;

use RuntimeException;

/**
 * Installs and removes a pre-push hook that runs `tutamen scan --hook`.
 *
 * The hook is managed as a clearly-marked block so it is append-friendly: an
 * existing (foreign) hook is never clobbered — our block is appended and, on
 * uninstall, only our block is removed. Re-installing replaces our block in
 * place (idempotent). Works for both native `.git/hooks/pre-push` and Husky
 * `.husky/pre-push`.
 */
final class HookInstaller
{
    public const STATUS_CREATED = 'created';

    public const STATUS_UPDATED = 'updated';

    public const STATUS_APPENDED = 'appended';

    private const MARKER_BEGIN = '# >>> tutamen managed block >>>';

    private const MARKER_END = '# <<< tutamen managed block <<<';

    public function install(string $repoRoot, bool $husky = false): string
    {
        $path = $this->hookPath($repoRoot, $husky);
        $this->ensureDirectory(dirname($path));

        $block = $this->block();
        $existing = is_file($path) ? (string) file_get_contents($path) : '';

        if ($existing === '') {
            $this->write($path, "#!/bin/sh\n".$block."\n");

            return self::STATUS_CREATED;
        }

        if ($this->containsBlock($existing)) {
            $this->write($path, $this->replaceBlock($existing, $block));

            return self::STATUS_UPDATED;
        }

        // Preserve the foreign hook, append ours after it.
        $this->write($path, rtrim($existing, "\n")."\n\n".$block."\n");

        return self::STATUS_APPENDED;
    }

    /**
     * Remove only the tutamen block. Returns true if a block was removed.
     */
    public function uninstall(string $repoRoot, bool $husky = false): bool
    {
        $path = $this->hookPath($repoRoot, $husky);

        if (! is_file($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        if (! $this->containsBlock($content)) {
            return false;
        }

        $remaining = trim($this->replaceBlock($content, ''));

        // If nothing meaningful is left (just a shebang, or empty), drop the
        // file entirely so we don't leave a dead hook behind.
        if ($remaining === '' || $remaining === '#!/bin/sh') {
            unlink($path);

            return true;
        }

        $this->write($path, $remaining."\n");

        return true;
    }

    public function hookPath(string $repoRoot, bool $husky): string
    {
        $root = rtrim($repoRoot, '/');

        return $husky ? $root.'/.husky/pre-push' : $root.'/.git/hooks/pre-push';
    }

    private function block(): string
    {
        return implode("\n", [
            self::MARKER_BEGIN,
            '# Tutamen scans your working tree before each push.',
            '#   Bypass once:        git push --no-verify',
            '#   Configure branches/severity in .tutamen.json',
            'tutamen scan --hook || exit $?',
            self::MARKER_END,
        ]);
    }

    private function containsBlock(string $content): bool
    {
        return str_contains($content, self::MARKER_BEGIN) && str_contains($content, self::MARKER_END);
    }

    private function replaceBlock(string $content, string $replacement): string
    {
        $pattern = '/\n*'.preg_quote(self::MARKER_BEGIN, '/').'.*?'.preg_quote(self::MARKER_END, '/')."\n*/s";

        $result = preg_replace($pattern, $replacement === '' ? "\n" : "\n".$replacement."\n", $content);

        return $result ?? $content;
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create hook directory: {$dir}");
        }
    }

    private function write(string $path, string $content): void
    {
        file_put_contents($path, $content);
        @chmod($path, 0755);
    }
}
