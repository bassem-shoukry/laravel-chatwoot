<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Support;

use BassamShoukry\LaravelChatwoot\Contracts\AccountResolver;
use BassamShoukry\LaravelChatwoot\Contracts\TokenVault;
use BassamShoukry\LaravelChatwoot\Exceptions\AccountNotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\ConfigurationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class AccountManager implements AccountResolver
{
    /**
     * Resolved contexts keyed by account name, with a fingerprint of the
     * config they were built from so runtime config changes (tenant
     * switching, token rotation, Octane) are picked up.
     *
     * @var array<string, array{0: string, 1: AccountContext}>
     */
    private array $cache = [];

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly TokenVault $vault,
    ) {}

    public function resolve(?string $name = null): AccountContext
    {
        $name ??= (string) $this->config->get('chatwoot.default_account', 'default');

        // Look up by array key: a dotted name must not resolve a nested path.
        $accounts = $this->config->get('chatwoot.accounts', []);
        $accountConfig = is_array($accounts) && array_key_exists($name, $accounts) ? $accounts[$name] : null;

        if (! is_array($accountConfig)) {
            throw AccountNotFoundException::for($name);
        }

        $allowLocal = (bool) $this->config->get('chatwoot.allow_local_urls', false);
        $fingerprint = hash('xxh128', serialize([$accountConfig, $allowLocal]));

        if (isset($this->cache[$name]) && $this->cache[$name][0] === $fingerprint) {
            return $this->cache[$name][1];
        }

        $url = (string) ($accountConfig['url'] ?? '');
        $token = (string) ($accountConfig['token'] ?? '');
        $accountId = (int) ($accountConfig['account_id'] ?? 0);

        if ($url === '') {
            throw ConfigurationException::missing("chatwoot.accounts.{$name}.url");
        }

        if ($token === '') {
            throw ConfigurationException::missing("chatwoot.accounts.{$name}.token");
        }

        if ($accountId <= 0) {
            throw ConfigurationException::missing("chatwoot.accounts.{$name}.account_id");
        }

        $this->guardAgainstUnsafeUrl($url, $allowLocal);

        $context = new AccountContext(
            name: $name,
            url: rtrim($url, '/'),
            token: $this->vault->decrypt($token),
            accountId: $accountId,
        );

        $this->cache[$name] = [$fingerprint, $context];

        return $context;
    }

    private function guardAgainstUnsafeUrl(string $url, bool $allowLocal): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException("Chatwoot account URL must use http or https; got [{$url}].");
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            throw new ConfigurationException("Chatwoot account URL is missing a host: [{$url}].");
        }

        if ($allowLocal) {
            return;
        }

        if (self::isLocalHost($host)) {
            throw new ConfigurationException("Chatwoot account URL points at a loopback or link-local host [{$host}]. Set chatwoot.allow_local_urls=true to override (test environments only).");
        }
    }

    /**
     * Loopback, unspecified and link-local (cloud metadata) addresses in any
     * spelling. Private RFC 1918 ranges stay allowed: self-hosted Chatwoot on
     * an internal network is a normal setup.
     */
    private static function isLocalHost(string $host): bool
    {
        $host = rtrim(strtolower(trim($host, '[]')), '.');

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = (string) inet_pton($host);

            // IPv4-mapped (::ffff:a.b.c.d): judge the embedded IPv4 address.
            if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
                return self::isLocalIpv4((string) inet_ntop(substr($packed, 12)));
            }

            return $packed === inet_pton('::')
                || $packed === inet_pton('::1')
                || (ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0x80); // fe80::/10
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::isLocalIpv4($host);
        }

        // Numeric shorthands curl resolves as IPv4 (127.1, 2130706433, 0x7f000001, 0).
        return preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+)){0,3}$/', $host) === 1;
    }

    private static function isLocalIpv4(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        foreach (['127.0.0.0/8', '0.0.0.0/8', '169.254.0.0/16'] as $cidr) {
            [$subnet, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($long & $mask) === ((int) ip2long($subnet) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
