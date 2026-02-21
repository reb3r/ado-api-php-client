<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Http;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;
use Reb3r\ADOAPC\Exceptions\Exception;

class AzureDevOpsHttpClient
{
    private Client $guzzle;

    public function __construct(
        private string $username,
        private string $password,
        ?Client $httpClient = null
    ) {
        $this->guzzle = $httpClient ?? new Client();
    }

    /**
     * @return array<string, string>
     */
    private function getAuthHeader(): array
    {
        if (empty($this->username)) {
            return ['Authorization' => 'Bearer ' . $this->password];
        }
        return ['Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)];
    }

    /**
     * @param array<string, string> $headers
     * @throws AuthenticationException
     * @throws Exception
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        $headers = array_merge($headers, $this->getAuthHeader());

        $response = $this->guzzle->get($url, [
            'headers' => $headers,
            'http_errors' => false
        ]);

        if ($response->getStatusCode() === 200) {
            return $response;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * @param array<string, string> $headers
     * @throws AuthenticationException
     * @throws Exception
     */
    public function post(string $url, string $body, array $headers = []): ResponseInterface
    {
        $headers = array_merge($headers, $this->getAuthHeader());

        $response = $this->guzzle->post($url, [
            'body' => $body,
            'headers' => $headers,
            'http_errors' => false
        ]);

        if ($response->getStatusCode() === 200) {
            return $response;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request failed: ' . $response->getStatusCode());
    }

    /**
     * @param array<string, string> $headers
     * @throws AuthenticationException
     * @throws Exception
     */
    public function patch(string $url, string $body, array $headers = []): ResponseInterface
    {
        $headers = array_merge($headers, $this->getAuthHeader());

        $response = $this->guzzle->patch($url, [
            'body' => $body,
            'headers' => $headers,
            'http_errors' => false
        ]);

        if ($response->getStatusCode() === 200) {
            return $response;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request failed: ' . $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function decodeJsonResponse(ResponseInterface $response, string $key = ''): mixed
    {
        $data = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode JSON response: ' . json_last_error_msg());
        }

        if ($key !== '' && isset($data[$key])) {
            return $data[$key];
        }

        return $data;
    }
}
