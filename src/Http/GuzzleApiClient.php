<?php

declare(strict_types=1);

namespace Tutamen\Cli\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * The real HTTP client, talking to /api/v1/scans with a Bearer token.
 */
final class GuzzleApiClient implements ApiClient
{
    public function __construct(private readonly ?Client $client = null)
    {
    }

    public function createScan(string $server, string $token, string $tarballPath, array $meta): array
    {
        $multipart = [
            ['name' => 'snapshot', 'contents' => $this->openFile($tarballPath), 'filename' => 'snapshot.tar.gz'],
            ['name' => 'project_name', 'contents' => $meta['project_name']],
        ];

        foreach (['branch', 'commit_sha'] as $key) {
            if (! empty($meta[$key])) {
                $multipart[] = ['name' => $key, 'contents' => (string) $meta[$key]];
            }
        }

        $response = $this->send($server, $token, 'POST', '/api/v1/scans', ['multipart' => $multipart]);

        $body = $this->decode($response);

        return [
            'id' => (string) ($body['id'] ?? ''),
            'status' => (string) ($body['status'] ?? 'queued'),
        ];
    }

    public function getScan(string $server, string $token, string $id): array
    {
        return $this->decode($this->send($server, $token, 'GET', '/api/v1/scans/'.rawurlencode($id)));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function send(string $server, string $token, string $method, string $path, array $options = []): ResponseInterface
    {
        $client = $this->client ?? new Client(['timeout' => 120]);

        try {
            return $client->request($method, rtrim($server, '/').$path, [
                ...$options,
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ],
                'http_errors' => true,
            ]);
        } catch (ConnectException $e) {
            throw new ApiException("Could not reach {$server}. Check the server URL and your connection.", 0, $e);
        } catch (GuzzleException $e) {
            throw new ApiException($this->friendlyError($e), 0, $e);
        }
    }

    /**
     * @return resource
     */
    private function openFile(string $path)
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new ApiException("Could not open snapshot for upload: {$path}");
        }

        return $handle;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function friendlyError(GuzzleException $e): string
    {
        if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
            $response = $e->getResponse();
            $status = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);
            $message = is_array($body) ? ($body['message'] ?? null) : null;

            if ($status === 401) {
                return 'Authentication failed. Run `tutamen auth` with a valid token.';
            }

            if (is_string($message) && $message !== '') {
                return $message;
            }

            return "Server returned HTTP {$status}.";
        }

        return $e->getMessage();
    }
}
