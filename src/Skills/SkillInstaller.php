<?php

declare(strict_types=1);

namespace Tutamen\Cli\Skills;

use RuntimeException;

/**
 * Installs the bundled `tutamen-security` agent skill into an AI coding
 * agent's skills directory. The skill ships inside this package (customers
 * only ever have the CLI, never the monorepo), and both Claude Code and Codex
 * CLI auto-discover the same SKILL.md format — they just look in different
 * directories:
 *
 *   Claude Code: .claude/skills/<name>/SKILL.md   (~/.claude/skills for --global)
 *   Codex CLI:   .codex/skills/<name>/SKILL.md    (~/.codex/skills  for --global)
 *
 * Writing our own managed file is idempotent: re-installing overwrites it
 * (that's how a `composer global update` ships a newer skill).
 */
final class SkillInstaller
{
    public const STATUS_CREATED = 'created';

    public const STATUS_UPDATED = 'updated';

    private const SKILL_NAME = 'tutamen-security';

    /** @var array<string, string> agent → skills dir, relative to the base */
    private const AGENT_DIRS = [
        'claude' => '.claude/skills',
        'codex' => '.codex/skills',
    ];

    public function __construct(
        private readonly string $home,
        private readonly string $cwd,
        private readonly ?string $sourcePath = null,
    ) {
    }

    /**
     * The agents this command knows how to target.
     *
     * @return list<string>
     */
    public static function agents(): array
    {
        return array_keys(self::AGENT_DIRS);
    }

    public static function isValidAgent(string $agent): bool
    {
        return isset(self::AGENT_DIRS[$agent]);
    }

    /**
     * The bundled SKILL.md contents (raw markdown, frontmatter included).
     */
    public function contents(): string
    {
        $source = $this->sourcePath ?? dirname(__DIR__, 2).'/resources/skills/'.self::SKILL_NAME.'/SKILL.md';

        if (! is_file($source)) {
            throw new RuntimeException("Bundled skill not found at {$source}. Reinstall tutamen/cli.");
        }

        return (string) file_get_contents($source);
    }

    /**
     * The directory the skill will be written into for $agent (project-level,
     * or the user home with $global).
     */
    public function targetDir(string $agent, bool $global): string
    {
        if (! self::isValidAgent($agent)) {
            throw new RuntimeException("Unknown agent '{$agent}'. Use one of: ".implode(', ', self::agents()).'.');
        }

        $base = rtrim($global ? $this->home : $this->cwd, '/');

        return $base.'/'.self::AGENT_DIRS[$agent].'/'.self::SKILL_NAME;
    }

    /**
     * Write the bundled skill into the resolved directory.
     *
     * @return array{status: string, path: string}
     */
    public function install(string $agent, bool $global): array
    {
        $dir = $this->targetDir($agent, $global);
        $file = $dir.'/SKILL.md';
        $existed = is_file($file);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create skills directory: {$dir}");
        }

        if (@file_put_contents($file, $this->contents()) === false) {
            throw new RuntimeException("Could not write the skill to {$file}");
        }

        return [
            'status' => $existed ? self::STATUS_UPDATED : self::STATUS_CREATED,
            'path' => $file,
        ];
    }
}
