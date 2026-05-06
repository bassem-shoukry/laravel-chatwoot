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
    /** @var array<string, AccountContext> */
    private array $cache = [];

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly TokenVault $vault,
    ) {}

    public function resolve(?string $name = null): AccountContext
    {
        $name ??= (string) $this->config->get('chatwoot.default_account', 'default');

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        /** @var array<string, mixed>|null $accountConfig */
        $accountConfig = $this->config->get("chatwoot.accounts.{$name}");

        if (! is_array($accountConfig)) {
            throw AccountNotFoundException::for($name);
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

        $this->guardAgainstUnsafeUrl($url);

        return $this->cache[$name] = new AccountContext(
            name: $name,
            url: rtrim($url, '/'),
            token: $this->vault->decrypt($token),
            accountId: $accountId,
        );
    }

    private function guardAgainstUnsafeUrl(string $url): void
    {
        if ((bool) $this->config->get('chatwoot.allow_local_urls', false)) {
            return;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException("Chatwoot account URL must use http or https; got [{$url}].");
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            throw new ConfigurationException("Chatwoot account URL is missing a host: [{$url}].");
        }

        $blocked = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array(strtolower($host), $blocked, true)) {
            throw new ConfigurationException("Chatwoot account URL points at a loopback host [{$host}]. Set chatwoot.allow_local_urls=true to override (test environments only).");
        }
    }
}
