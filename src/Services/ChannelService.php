<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class ChannelService
{
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('chatwoot.channels', []);
    }

    /**
     * Get channel configuration.
     */
    public function getChannelConfig(string $channel): array
    {
        return $this->config[$channel] ?? [];
    }

    /**
     * Get all available channels.
     */
    public function getAvailableChannels(): array
    {
        return array_keys($this->config);
    }

    /**
     * Check if a channel is supported.
     */
    public function isChannelSupported(string $channel): bool
    {
        return isset($this->config[$channel]);
    }

    /**
     * Get maximum message size for a channel.
     */
    public function getMaxMessageSize(string $channel): int
    {
        $channelConfig = $this->getChannelConfig($channel);

        return $channelConfig['max_message_size'] ?? 10000;
    }

    /**
     * Check if channel supports attachments.
     */
    public function supportsAttachments(string $channel): bool
    {
        $channelConfig = $this->getChannelConfig($channel);

        return $channelConfig['supports_attachments'] ?? false;
    }

    /**
     * Check if channel supports templates.
     */
    public function supportsTemplates(string $channel): bool
    {
        $channelConfig = $this->getChannelConfig($channel);

        return $channelConfig['supports_templates'] ?? true;
    }

    /**
     * Get outbound restrictions for a channel.
     */
    public function getOutboundRestrictions(string $channel): string
    {
        $channelConfig = $this->getChannelConfig($channel);

        return $channelConfig['outbound_restrictions'] ?? 'none';
    }

    /**
     * Get promotional message window in hours.
     */
    public function getPromotionalWindow(string $channel): ?int
    {
        $channelConfig = $this->getChannelConfig($channel);

        return $channelConfig['promotional_window'] ?? null;
    }

    /**
     * Get human agent window in hours (for Facebook/Instagram).
     */
    public function getHumanAgentWindow(string $channel): ?int
    {
        $channelConfig = $this->getChannelConfig($channel);

        return $channelConfig['human_agent_window'] ?? null;
    }

    /**
     * Validate message against channel restrictions.
     */
    public function validateMessage(string $channel, array $messageData): array
    {
        $errors = [];
        $channelConfig = $this->getChannelConfig($channel);

        if (empty($channelConfig)) {
            $errors[] = "Channel '$channel' is not supported";

            return ['valid' => false, 'errors' => $errors];
        }

        // Check message size
        $content = $messageData['content'] ?? '';
        $maxSize = $this->getMaxMessageSize($channel);
        if (strlen($content) > $maxSize) {
            $errors[] = "Message exceeds maximum size of $maxSize characters for $channel";
        }

        // Check attachments support
        if (! empty($messageData['attachments']) && ! $this->supportsAttachments($channel)) {
            $errors[] = "Channel '$channel' does not support attachments";
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if promotional message can be sent based on last interaction time.
     */
    public function canSendPromotionalMessage(string $channel, ?Carbon $lastInteractionTime = null): bool
    {
        $restrictions = $this->getOutboundRestrictions($channel);

        // No restrictions
        if ($restrictions === 'none') {
            return true;
        }

        // If no last interaction time provided, assume we can't send promotional
        if (! $lastInteractionTime) {
            return false;
        }

        $now = Carbon::now();

        // Check promotional window
        $promotionalWindow = $this->getPromotionalWindow($channel);
        if ($promotionalWindow && $lastInteractionTime->addHours($promotionalWindow)->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if message can be sent with human agent tag.
     */
    public function canSendWithHumanAgent(string $channel, ?Carbon $lastInteractionTime = null): bool
    {
        $humanAgentWindow = $this->getHumanAgentWindow($channel);

        // If channel doesn't support human agent extension
        if (! $humanAgentWindow) {
            return false;
        }

        // If no last interaction time provided, assume we can't send
        if (! $lastInteractionTime) {
            return false;
        }

        $now = Carbon::now();

        return $lastInteractionTime->addHours($humanAgentWindow)->isFuture();
    }

    /**
     * Check if template message is required for the channel.
     */
    public function requiresTemplateMessage(string $channel, ?Carbon $lastInteractionTime = null): bool
    {
        $restrictions = $this->getOutboundRestrictions($channel);

        // WhatsApp requires templates after 24 hours
        if ($restrictions === 'template_only_after_24h') {
            if (! $lastInteractionTime) {
                return true; // Assume template required if no interaction time
            }

            $templateRequiredAfter = $this->config[$channel]['template_required_after'] ?? 24;

            return $lastInteractionTime->addHours($templateRequiredAfter)->isPast();
        }

        return false;
    }

    /**
     * Get message sending strategy for a channel.
     */
    public function getMessageStrategy(string $channel, ?Carbon $lastInteractionTime = null, bool $isPromotional = false): array
    {
        $strategy = [
            'can_send'            => true,
            'requires_template'   => false,
            'can_use_human_agent' => false,
            'restrictions'        => [],
            'recommendations'     => [],
        ];

        if (! $this->isChannelSupported($channel)) {
            $strategy['can_send'] = false;
            $strategy['restrictions'][] = "Channel '$channel' is not supported";

            return $strategy;
        }

        $restrictions = $this->getOutboundRestrictions($channel);

        switch ($restrictions) {
            case 'none':
                // No restrictions
                break;

            case 'template_only_after_24h':
                // WhatsApp restrictions
                if ($this->requiresTemplateMessage($channel, $lastInteractionTime)) {
                    $strategy['requires_template'] = true;
                    if ($isPromotional) {
                        $strategy['restrictions'][] = 'Only WhatsApp approved template messages allowed after 24 hours';
                    }
                }

                break;

            case 'promotional_24h_or_7d_human_agent':
                // Facebook/Instagram restrictions
                if ($isPromotional && ! $this->canSendPromotionalMessage($channel, $lastInteractionTime)) {
                    if ($this->canSendWithHumanAgent($channel, $lastInteractionTime)) {
                        $strategy['can_use_human_agent'] = true;
                        $strategy['recommendations'][] = 'Consider using human_agent tag to extend messaging window to 7 days';
                    } else {
                        $strategy['can_send'] = false;
                        $strategy['restrictions'][] = 'Promotional messages not allowed after 24 hours (or 7 days with human_agent)';
                    }
                }

                break;

            case 'verified_contacts_only':
                // Live chat restrictions
                $strategy['recommendations'][] = 'Can only create outbound conversations to verified contacts';

                break;
        }

        return $strategy;
    }

    /**
     * Get channel capabilities summary.
     */
    public function getChannelCapabilities(string $channel): array
    {
        if (! $this->isChannelSupported($channel)) {
            return ['supported' => false];
        }

        $config = $this->getChannelConfig($channel);

        return [
            'supported'               => true,
            'max_message_size'        => $config['max_message_size'],
            'supports_attachments'    => $config['supports_attachments'],
            'supports_templates'      => $config['supports_templates'],
            'outbound_restrictions'   => $config['outbound_restrictions'],
            'promotional_window'      => $config['promotional_window'],
            'human_agent_window'      => $config['human_agent_window'] ?? null,
            'template_required_after' => $config['template_required_after'] ?? null,
        ];
    }

    /**
     * Get optimal channel for a message type.
     */
    public function getOptimalChannel(array $availableChannels, string $messageType = 'regular'): ?string
    {
        if (empty($availableChannels)) {
            return null;
        }

        // Priority order based on message type
        $priorities = [
            'promotional'  => ['email', 'sms', 'whatsapp', 'telegram', 'facebook', 'instagram', 'live_chat'],
            'notification' => ['sms', 'email', 'telegram', 'whatsapp', 'facebook', 'instagram', 'live_chat'],
            'support'      => ['live_chat', 'email', 'telegram', 'facebook', 'instagram', 'whatsapp', 'sms'],
            'regular'      => ['email', 'telegram', 'live_chat', 'sms', 'whatsapp', 'facebook', 'instagram'],
        ];

        $priorityList = $priorities[$messageType] ?? $priorities['regular'];

        // Find the first available channel in priority order
        foreach ($priorityList as $channel) {
            if (in_array($channel, $availableChannels) && $this->isChannelSupported($channel)) {
                return $channel;
            }
        }

        // If no priority match, return first available supported channel
        foreach ($availableChannels as $channel) {
            if ($this->isChannelSupported($channel)) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Format message content for specific channel.
     */
    public function formatMessageForChannel(string $channel, array $messageData): array
    {
        $maxSize = $this->getMaxMessageSize($channel);
        $content = $messageData['content'] ?? '';

        // Truncate content if too long
        if (strlen($content) > $maxSize) {
            $content = substr($content, 0, $maxSize - 3) . '...';
            $messageData['content'] = $content;
        }

        // Remove attachments if not supported
        if (! $this->supportsAttachments($channel)) {
            unset($messageData['attachments']);
        }

        // Add channel-specific formatting
        switch ($channel) {
            case 'whatsapp':
                // WhatsApp-specific formatting
                break;

            case 'facebook':
            case 'instagram':
                // Social media formatting
                break;

            case 'email':
                // Email formatting
                break;
        }

        return $messageData;
    }
}
