<?php

declare(strict_types=1);

/**
 * Minimal client for the Adobe PDF Services "Extract PDF" REST API (free tier works).
 *
 * Flow: token -> upload asset -> start extract job -> poll -> download structuredData.json
 * https://developer.adobe.com/document-services/docs/overview/pdf-services-api/
 */
final class AdobePdfExtract
{
    private const POLL_INTERVAL_SECONDS = 2;
    private const POLL_TIMEOUT_SECONDS = 90;

    private string $baseUrl;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        string $region = 'us'
    ) {
        // Documents are processed in the chosen region (us or eu)
        $this->baseUrl = $region === 'eu' ? 'https://pdf-services-ew1.adobe.io' : 'https://pdf-services.adobe.io';
    }

    /**
     * Run Extract on a local PDF and return the decoded structuredData.json.
     */
    public function extract(string $pdfPath): array
    {
        $token = $this->getAccessToken();
        $asset = $this->uploadAsset($token, $pdfPath);
        $jobUrl = $this->startExtractJob($token, $asset);
        $result = $this->waitForJob($token, $jobUrl);

        return $this->downloadStructuredData($result);
    }

    private function getAccessToken(): string
    {
        $response = $this->request('POST', $this->baseUrl . '/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]));

        $token = $this->json($response)['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new AdobePdfExtractException('Adobe did not return an access token.', $response['status']);
        }

        return $token;
    }

    private function uploadAsset(string $token, string $pdfPath): string
    {
        $response = $this->request('POST', $this->baseUrl . '/assets', $this->apiHeaders($token, [
            'Content-Type: application/json',
        ]), json_encode(['mediaType' => 'application/pdf']));

        $data = $this->json($response);
        if (empty($data['uploadUri']) || empty($data['assetID'])) {
            throw new AdobePdfExtractException('Adobe did not return an upload location.', $response['status']);
        }

        // The upload URI is pre-signed cloud storage: no Adobe headers here
        $this->request('PUT', $data['uploadUri'], ['Content-Type: application/pdf'], (string) file_get_contents($pdfPath));

        return (string) $data['assetID'];
    }

    private function startExtractJob(string $token, string $assetId): string
    {
        $response = $this->request('POST', $this->baseUrl . '/operation/extractpdf', $this->apiHeaders($token, [
            'Content-Type: application/json',
        ]), json_encode([
            'assetID' => $assetId,
            'elementsToExtract' => ['text'],
        ]));

        $location = $response['headers']['location'] ?? '';
        if ($location === '') {
            throw new AdobePdfExtractException('Adobe did not return a job location.', $response['status']);
        }

        return $location;
    }

    private function waitForJob(string $token, string $jobUrl): array
    {
        $deadline = time() + self::POLL_TIMEOUT_SECONDS;

        while (true) {
            $data = $this->json($this->request('GET', $jobUrl, $this->apiHeaders($token)));
            $status = $data['status'] ?? '';

            if ($status === 'done') {
                return $data;
            }
            if ($status === 'failed') {
                $message = $data['error']['message'] ?? 'Adobe could not process this PDF.';
                throw new AdobePdfExtractException((string) $message, 422);
            }
            if (time() >= $deadline) {
                throw new AdobePdfExtractException('Adobe took too long to process this PDF.', 504);
            }

            sleep(self::POLL_INTERVAL_SECONDS);
        }
    }

    /**
     * "content" is structuredData.json itself; "resource" is the zip package that also contains it.
     */
    private function downloadStructuredData(array $job): array
    {
        $uri = $job['content']['downloadUri'] ?? $job['resource']['downloadUri'] ?? $job['asset']['downloadUri'] ?? null;
        if (!is_string($uri) || $uri === '') {
            throw new AdobePdfExtractException('Adobe did not return a download link.', 502);
        }

        $body = $this->request('GET', $uri, [])['body'];

        if (str_starts_with($body, 'PK')) {
            $body = $this->readStructuredDataFromZip($body);
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['elements'])) {
            throw new AdobePdfExtractException('Adobe returned an unexpected result.', 502);
        }

        return $data;
    }

    private function readStructuredDataFromZip(string $zipBytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'adobe_extract_');
        file_put_contents($path, $zipBytes);

        try {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                throw new AdobePdfExtractException('Could not open the result package from Adobe.', 502);
            }
            $json = $zip->getFromName('structuredData.json');
            $zip->close();
        } finally {
            @unlink($path);
        }

        if ($json === false) {
            throw new AdobePdfExtractException('The result package from Adobe has no structuredData.json.', 502);
        }

        return $json;
    }

    private function apiHeaders(string $token, array $extra = []): array
    {
        return array_merge([
            'Authorization: Bearer ' . $token,
            'x-api-key: ' . $this->clientId,
        ], $extra);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $responseHeaders = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($responseBody === false) {
            throw new AdobePdfExtractException('Could not reach Adobe: ' . $error, 502);
        }
        if ($status >= 400) {
            error_log("Adobe PDF Services {$method} {$url} -> {$status}: " . substr((string) $responseBody, 0, 500));
            $errorCode = (string) (json_decode((string) $responseBody, true)['error']['code'] ?? '');
            throw new AdobePdfExtractException(self::messageForStatus($status, $errorCode), $status);
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => (string) $responseBody];
    }

    private function json(array $response): array
    {
        $data = json_decode($response['body'], true);
        return is_array($data) ? $data : [];
    }

    private static function messageForStatus(int $status, string $errorCode): string
    {
        return match (true) {
            $status === 401, $status === 403, str_starts_with($errorCode, 'invalid_client'), $errorCode === 'invalid_secret'
                => 'The Adobe PDF Services credentials were rejected.',
            $status === 429 => 'The monthly Adobe PDF Services quota has been used up. Please try again later.',
            $status === 413 => 'This PDF is too large for Adobe PDF Services.',
            default => "Adobe PDF Services returned an error ({$status}).",
        };
    }
}

final class AdobePdfExtractException extends RuntimeException
{
}
