<?php

namespace App\Services\ProductImport;

use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Downloads product photos listed in the import file. Only public http(s) hosts are allowed
 * (no localhost / private networks, re-checked on every redirect), the response must be a real
 * JPG/PNG/WEBP/GIF image under the size limit, and the file type is taken from the content.
 */
class RemoteImageFetcher
{
    /** @return array{0: string, 1: string} [binary contents, mime type] */
    public function fetch(string $url): array
    {
        $this->assertAllowed($url);
        $maxBytes = config('imports.max_image_kb', 5120) * 1024;

        try {
            $response = Http::timeout(config('imports.image_timeout', 20))
                ->connectTimeout(8)
                ->withHeaders(['User-Agent' => 'MotoGears-Importer/1.0', 'Accept' => 'image/*'])
                ->withOptions(['allow_redirects' => [
                    'max' => 3, 'protocols' => ['http', 'https'],
                    'on_redirect' => function (RequestInterface $req, ResponseInterface $res, UriInterface $uri) {
                        $this->assertAllowed((string) $uri);
                    },
                ]])
                ->get($url);
        } catch (ImageFetchException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $inner = $e->getPrevious();
            if ($inner instanceof ImageFetchException) {
                throw $inner;
            }
            throw new ImageFetchException('the server could not be reached');
        }

        if (! $response->successful()) {
            throw new ImageFetchException('the server answered '.$response->status());
        }
        $body = $response->body();
        if ($body === '') {
            throw new ImageFetchException('the file is empty');
        }
        if (strlen($body) > $maxBytes) {
            throw new ImageFetchException('the image is larger than '.round($maxBytes / 1048576, 1).' MB');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        if (! isset(ImageUploadService::MIMES[$mime])) {
            throw new ImageFetchException('it is not a JPG, PNG, WEBP or GIF image');
        }
        $size = @getimagesizefromstring($body);
        if (! $size || $size[0] > 5000 || $size[1] > 5000) {
            throw new ImageFetchException($size ? 'the image is larger than 5000×5000 pixels' : 'the image could not be read');
        }

        return [$body, $mime];
    }

    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ImageFetchException('only http(s) links are allowed');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ImageFetchException('links with a username/password are not allowed');
        }
        if (config('imports.allow_private_image_hosts')) {
            return;
        }
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );
        if (! $ips) {
            throw new ImageFetchException('the host name could not be found');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ImageFetchException('links to private or local network addresses are not allowed');
            }
        }
    }
}
