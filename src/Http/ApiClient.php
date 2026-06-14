<?php

declare(strict_types=1);

namespace Tutamen\Cli\Http;

/**
 * The CLI's view of the Tutamen scan API. An interface so the HTTP layer can be
 * faked in tests (the ScannerRunner/Fake precedent on the server side).
 */
interface ApiClient
{
    /**
     * Upload a snapshot and queue a scan.
     *
     * @param  array{project_name: string, branch?: ?string, commit_sha?: ?string}  $meta
     * @return array{id: string, status: string}
     *
     * @throws ApiException on transport, auth or server error
     */
    public function createScan(string $server, string $token, string $tarballPath, array $meta): array;

    /**
     * Fetch a scan's current state (status, and findings/stats once finished).
     *
     * @return array<string, mixed>
     *
     * @throws ApiException on transport, auth or server error
     */
    public function getScan(string $server, string $token, string $id): array;
}
