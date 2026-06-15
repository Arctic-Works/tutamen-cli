<?php

declare(strict_types=1);

use Symfony\Component\Console\Tester\CommandTester;
use Tutamen\Cli\Console\SkillInstallCommand;
use Tutamen\Cli\Skills\SkillInstaller;

beforeEach(function () {
    $this->home = tempDir('tutamen-skill-home-');
    $this->project = tempDir('tutamen-skill-project-');
    // A fake bundled skill so the test doesn't depend on the real resource path.
    $this->source = tempDir('tutamen-skill-src-').'/SKILL.md';
    file_put_contents($this->source, "---\nname: tutamen-security\n---\nBootstrap only.\n");
});

afterEach(function () {
    removeDir($this->home);
    removeDir($this->project);
    removeDir(dirname($this->source));
});

function makeInstaller(string $home, string $cwd, string $source): SkillInstaller
{
    return new SkillInstaller($home, $cwd, $source);
}

it('installs the skill into a project .claude/skills directory by default', function () {
    $installer = makeInstaller($this->home, $this->project, $this->source);
    $result = $installer->install('claude', false);

    expect($result['status'])->toBe(SkillInstaller::STATUS_CREATED)
        ->and($result['path'])->toBe($this->project.'/.claude/skills/tutamen-security/SKILL.md')
        ->and(file_exists($result['path']))->toBeTrue()
        ->and(file_get_contents($result['path']))->toContain('tutamen-security');
});

it('targets .codex/skills for the codex agent and the home dir when global', function () {
    $installer = makeInstaller($this->home, $this->project, $this->source);

    expect($installer->targetDir('codex', false))->toBe($this->project.'/.codex/skills/tutamen-security')
        ->and($installer->targetDir('codex', true))->toBe($this->home.'/.codex/skills/tutamen-security')
        ->and($installer->targetDir('claude', true))->toBe($this->home.'/.claude/skills/tutamen-security');
});

it('reports updated when the skill already exists', function () {
    $installer = makeInstaller($this->home, $this->project, $this->source);

    expect($installer->install('claude', false)['status'])->toBe(SkillInstaller::STATUS_CREATED)
        ->and($installer->install('claude', false)['status'])->toBe(SkillInstaller::STATUS_UPDATED);
});

it('rejects an unknown agent', function () {
    expect(fn () => makeInstaller($this->home, $this->project, $this->source)->targetDir('cursor', false))
        ->toThrow(RuntimeException::class);
});

it('command installs for both agents by default', function () {
    $command = new SkillInstallCommand(makeInstaller($this->home, $this->project, $this->source));
    $tester = new CommandTester($command);
    $tester->execute([]);

    expect($tester->getStatusCode())->toBe(0)
        ->and(file_exists($this->project.'/.claude/skills/tutamen-security/SKILL.md'))->toBeTrue()
        ->and(file_exists($this->project.'/.codex/skills/tutamen-security/SKILL.md'))->toBeTrue()
        ->and($tester->getDisplay())->toContain('Claude Code')
        ->and($tester->getDisplay())->toContain('Codex');
});

it('command --agent installs for only the named agent', function () {
    $command = new SkillInstallCommand(makeInstaller($this->home, $this->project, $this->source));
    $tester = new CommandTester($command);
    $tester->execute(['--agent' => 'codex']);

    expect($tester->getStatusCode())->toBe(0)
        ->and(file_exists($this->project.'/.codex/skills/tutamen-security/SKILL.md'))->toBeTrue()
        ->and(is_dir($this->project.'/.claude'))->toBeFalse();
});

it('command --print writes the skill to stdout without installing', function () {
    $command = new SkillInstallCommand(makeInstaller($this->home, $this->project, $this->source));
    $tester = new CommandTester($command);
    $tester->execute(['--print' => true]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('name: tutamen-security')
        ->and(is_dir($this->project.'/.claude'))->toBeFalse();
});

it('command rejects an unknown --agent', function () {
    $command = new SkillInstallCommand(makeInstaller($this->home, $this->project, $this->source));
    $tester = new CommandTester($command);
    $tester->execute(['--agent' => 'cursor']);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Unknown --agent');
});

it('ships a real bundled skill resolvable from the package', function () {
    // No source override → exercises the real resources/ path that ships in the package.
    $real = new SkillInstaller($this->home, $this->project);

    expect($real->contents())->toContain('name: tutamen-security')
        ->and($real->contents())->toContain('tutamen scan --agent');
});
