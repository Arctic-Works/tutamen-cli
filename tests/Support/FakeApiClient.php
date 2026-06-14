<?php

declare(strict_types=1);

namespace Tutamen\Cli\Tests\Support;

use Tutamen\Cli\Http\ApiClient;
use Tutamen\Cli\Http\ApiException;

/**
 * In-memory ApiClient for tests: records the create call and replays a canned
 * sequence of getScan() envelopes (so polling can be exercised without sleep).
 */
final class FakeApiClient implements ApiClient
{
    public int $createCalls = 0;

    public ?string $uploadedTarball = null;

    /** @var array<string, mixed>|null */
    public ?array $createMeta = null;

    public int $promptCalls = 0;

    /** @var list<array<string, mixed>> */
    private array $statusSequence;

    /**
     * @param  list<array<string, mixed>>  $statusSequence  envelopes returned by successive getScan() calls
     * @param  array{version: int, prompt: string}  $prompt  the agent prompt returned by getAgentPrompt()
     */
    public function __construct(
        array $statusSequence,
        private readonly bool $failCreate = false,
        private readonly array $prompt = ['version' => 1, 'prompt' => 'Follow these steps.'],
    ) {
        $this->statusSequence = $statusSequence;
    }

    public function createScan(string $server, string $token, string $tarballPath, array $meta): array
    {
        if ($this->failCreate) {
            throw new ApiException('Upload rejected.');
        }

        $this->createCalls++;
        $this->uploadedTarball = $tarballPath;
        $this->createMeta = $meta;

        return ['id' => 'cli-scan-123', 'status' => 'queued'];
    }

    public function getScan(string $server, string $token, string $id): array
    {
        return count($this->statusSequence) > 1
            ? array_shift($this->statusSequence)
            : $this->statusSequence[0];
    }

    public function getAgentPrompt(string $server, string $token): array
    {
        $this->promptCalls++;

        return $this->prompt;
    }
}
