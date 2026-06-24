<?php declare(strict_types=1);

namespace Kennofizet\AppHub\Tests\Unit\Modules\Catalog;

use Kennofizet\AppHub\Modules\Catalog\Support\AppManifestApiUrl;
use PHPUnit\Framework\TestCase;

final class AppManifestApiUrlTest extends TestCase
{
    public function test_normalize_list_and_legacy_api_base_url(): void
    {
        $urls = AppManifestApiUrl::fromManifest([
            'api_base_url' => 'https://api.example.com/',
            'api_urls' => ['https://tools.reg.local/apps/demo', 'http://localhost:3000'],
        ]);

        $this->assertSame([
            'https://api.example.com',
            'https://tools.reg.local/apps/demo',
            'http://localhost:3000',
        ], $urls);
    }

    public function test_matches_allowed_origin_and_path_prefix(): void
    {
        $allowed = ['https://tools.reg.local/apps/demo'];

        $this->assertTrue(AppManifestApiUrl::matchesAllowed(
            'https://tools.reg.local/apps/demo/verify',
            $allowed,
        ));
        $this->assertFalse(AppManifestApiUrl::matchesAllowed(
            'https://evil.example.com/apps/demo',
            $allowed,
        ));
    }

    public function test_rejects_urls_with_credentials(): void
    {
        $this->assertNull(AppManifestApiUrl::normalizeSingle('https://user:pass@api.example.com'));
    }

    public function test_has_api_urls_when_declared(): void
    {
        $this->assertTrue(AppManifestApiUrl::hasApiUrls([
            'api_urls' => ['http://localhost:51732'],
        ]));
        $this->assertFalse(AppManifestApiUrl::hasApiUrls(['slug' => 'demo']));
        $this->assertFalse(AppManifestApiUrl::hasApiUrls(['api_urls' => []]));
    }

    public function test_client_ip_matches_literal_host_ip(): void
    {
        $this->assertTrue(AppManifestApiUrl::clientMatchesAllowedHosts(
            '10.0.0.5',
            ['https://10.0.0.5/apps/demo'],
        ));
    }

    public function test_client_ip_matches_localhost_via_dns(): void
    {
        $this->assertTrue(AppManifestApiUrl::clientMatchesAllowedHosts(
            '127.0.0.1',
            ['http://localhost:3000'],
        ));
    }

    public function test_publisher_origin_port_must_match_api_urls(): void
    {
        $this->assertFalse(AppManifestApiUrl::publisherOriginMatchesAllowed(
            'http://localhost:51732',
            ['http://localhost:5132'],
        ));
        $this->assertTrue(AppManifestApiUrl::publisherOriginMatchesAllowed(
            'http://localhost:5132',
            ['http://localhost:5132'],
        ));
        $this->assertTrue(AppManifestApiUrl::publisherOriginMatchesAllowed(
            'https://tools.reg.local',
            ['https://tools.reg.local/apps/demo'],
        ));
    }

    public function test_publisher_origin_from_request_header(): void
    {
        $request = \Illuminate\Http\Request::create(
            'http://example.test/bridge/notify',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_APPHUB_PUBLISHER_ORIGIN' => 'http://localhost:51732'],
        );

        $this->assertSame(
            'http://localhost:51732',
            AppManifestApiUrl::publisherOriginFromRequest($request),
        );
    }

    public function test_publisher_origin_rejects_spoofed_header_without_proxy_secret(): void
    {
        $request = \Illuminate\Http\Request::create(
            'http://example.test/bridge/user',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_X_APPHUB_PUBLISHER_ORIGIN' => 'http://localhost:5132',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
        );

        $allowed = ['http://localhost:5132'];
        $result = AppManifestApiUrl::validateLoopbackBridgeProxyAttestation(
            $request,
            $allowed,
            'apphub-local-bridge-dev',
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Bridge-Proxy-Secret', $result['error']);
    }

    public function test_loopback_attestation_rejects_wrong_publisher_port(): void
    {
        $request = \Illuminate\Http\Request::create(
            'http://example.test/bridge/notify',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_X_APPHUB_BRIDGE_PROXY_SECRET' => 'apphub-local-bridge-dev',
                'HTTP_X_APPHUB_PUBLISHER_ORIGIN' => 'http://localhost:51732',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
        );

        $result = AppManifestApiUrl::validateLoopbackBridgeProxyAttestation(
            $request,
            ['http://localhost:5132'],
            'apphub-local-bridge-dev',
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('Publisher origin does not match manifest api_urls', $result['error']);
    }

    public function test_loopback_attestation_accepts_matching_proxy_origin(): void
    {
        $request = \Illuminate\Http\Request::create(
            'http://example.test/bridge/notify',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_X_APPHUB_BRIDGE_PROXY_SECRET' => 'apphub-local-bridge-dev',
                'HTTP_X_APPHUB_PUBLISHER_ORIGIN' => 'http://localhost:51732',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
        );

        $result = AppManifestApiUrl::validateLoopbackBridgeProxyAttestation(
            $request,
            ['http://localhost:51732'],
            'apphub-local-bridge-dev',
        );

        $this->assertTrue($result['ok']);
    }

    public function test_allowed_urls_are_loopback_only(): void
    {
        $this->assertTrue(AppManifestApiUrl::allowedUrlsAreLoopbackOnly(['http://localhost:51732']));
        $this->assertFalse(AppManifestApiUrl::allowedUrlsAreLoopbackOnly([
            'http://localhost:51732',
            'https://api.example.com',
        ]));
    }

    public function test_loopback_attestation_skipped_when_secret_unset(): void
    {
        $request = \Illuminate\Http\Request::create('http://example.test/bridge/user', 'GET');

        $result = AppManifestApiUrl::validateLoopbackBridgeProxyAttestation(
            $request,
            ['http://localhost:51732'],
            '',
        );

        $this->assertTrue($result['ok']);
    }

    public function test_client_ip_rejects_unknown_source(): void
    {
        $this->assertFalse(AppManifestApiUrl::clientMatchesAllowedHosts(
            '203.0.113.9',
            ['https://10.0.0.5/apps/demo'],
        ));
    }

    public function test_request_uses_ip_only_and_ignores_spoofed_origin(): void
    {
        $request = \Illuminate\Http\Request::create(
            'http://example.test/bridge/user',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'http://localhost:51732',
                'REMOTE_ADDR' => '203.0.113.9',
            ],
        );

        $this->assertFalse(AppManifestApiUrl::requestMatchesAllowed(
            $request,
            ['http://localhost:51732'],
        ));
    }

    public function test_connect_src_origins_from_api_urls(): void
    {
        $this->assertEqualsCanonicalizing(
            ['http://localhost:51732', 'http://127.0.0.1:51732', 'https://tools.reg.local'],
            AppManifestApiUrl::connectSrcOrigins([
                'http://localhost:51732',
                'https://tools.reg.local/apps/demo',
            ]),
        );
    }

    public function test_assert_production_safe_rejects_localhost_when_disabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('localhost or loopback');

        AppManifestApiUrl::assertProductionSafe([
            'api_urls' => ['http://localhost:51732'],
        ], false);
    }

    public function test_client_matches_pinned_ips_when_enabled(): void
    {
        $this->assertTrue(AppManifestApiUrl::clientMatchesAllowedHosts(
            '10.0.0.5',
            ['https://tools.example.com'],
            ['10.0.0.5', '10.0.0.6'],
            true,
        ));
        $this->assertFalse(AppManifestApiUrl::clientMatchesAllowedHosts(
            '203.0.113.9',
            ['https://tools.example.com'],
            ['10.0.0.5'],
            true,
        ));
    }

    public function test_assert_production_safe_allows_localhost_when_enabled(): void
    {
        AppManifestApiUrl::assertProductionSafe([
            'api_urls' => ['http://localhost:51732'],
        ], true);

        $this->addToAssertionCount(1);
    }
}
