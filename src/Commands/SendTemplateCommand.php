<?php

namespace BassamShoukry\LaravelChatwoot\Commands;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;
use BassamShoukry\LaravelChatwoot\Services\TemplateService;
use Illuminate\Console\Command;

class SendTemplateCommand extends Command
{
    public $signature = 'chatwoot:send-template 
                        {account : Account key from configuration}
                        {inbox : Inbox key from account configuration}
                        {template : Template key to send}
                        {contact-identifier : Contact identifier (email, phone, or unique ID)}
                        {--variables= : JSON string of template variables}
                        {--contact-name= : Contact name}
                        {--contact-email= : Contact email}
                        {--contact-phone= : Contact phone number}
                        {--conversation-id= : Existing conversation ID (optional)}
                        {--delay=0 : Delay in seconds before sending (for queue)}
                        {--queue : Send via queue instead of immediately}
                        {--preview : Preview the processed template without sending}
                        {--dry-run : Validate all parameters without sending}';
    public $description = 'Send a template message via Chatwoot API';
    protected AccountManager $accountManager;
    protected InboxManager $inboxManager;
    protected MessageService $messageService;
    protected TemplateService $templateService;

    public function __construct(
        AccountManager $accountManager,
        InboxManager $inboxManager,
        MessageService $messageService,
        TemplateService $templateService
    ) {
        parent::__construct();
        $this->accountManager = $accountManager;
        $this->inboxManager = $inboxManager;
        $this->messageService = $messageService;
        $this->templateService = $templateService;
    }

    public function handle(): int
    {
        $account = $this->argument('account');
        $inbox = $this->argument('inbox');
        $template = $this->argument('template');
        $contactIdentifier = $this->argument('contact-identifier');

        // Parse variables from JSON string
        $variables = [];
        if ($this->option('variables')) {
            try {
                $variables = json_decode($this->option('variables'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->error('Invalid JSON for variables: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('🚀 Preparing to send template message...');
        $this->newLine();

        try {
            // Set account and inbox context
            $this->accountManager->account($account);
            $this->inboxManager->inbox($inbox);

            $this->info('📍 Context:');
            $this->info("   Account: $account");
            $this->info("   Inbox: $inbox");
            $this->info("   Template: $template");
            $this->info("   Contact: $contactIdentifier");

            if (! empty($variables)) {
                $this->info('   Variables: ' . json_encode($variables));
            }

            $this->newLine();

            // Validate template exists and process it
            $this->info('📝 Validating and processing template...');

            $templateValidation = $this->templateService->validateTemplate($template, $variables, $account, $inbox);
            if (! $templateValidation['valid']) {
                $this->error('❌ Template validation failed:');
                foreach ($templateValidation['errors'] as $error) {
                    $this->error("   • $error");
                }

                return self::FAILURE;
            }

            $processedTemplate = $this->templateService->processTemplate($template, $variables, $account, $inbox);

            $this->info('✅ Template processed successfully');

            // Show preview if requested
            if ($this->option('preview')) {
                $this->info('👀 Template Preview:');
                $this->info('   Content: ' . ($processedTemplate['content']['text'] ?? 'N/A'));
                $this->info('   Type: ' . ($processedTemplate['content']['type'] ?? 'text'));
                if (isset($processedTemplate['content']['attachments'])) {
                    $this->info('   Attachments: ' . count($processedTemplate['content']['attachments']));
                }
                $this->newLine();
            }

            // Stop here if dry run
            if ($this->option('dry-run')) {
                $this->info('🧪 Dry run completed - no message sent');

                return self::SUCCESS;
            }

            // Prepare contact data
            $contactData = [
                'identifier'   => $contactIdentifier,
                'name'         => $this->option('contact-name') ?: $contactIdentifier,
                'email'        => $this->option('contact-email'),
                'phone_number' => $this->option('contact-phone'),
            ];

            // Filter out null values
            $contactData = array_filter($contactData, fn ($value) => $value !== null);

            // Prepare message options
            $options = array_filter([
                'contact'         => $contactData,
                'conversation_id' => $this->option('conversation-id'),
            ]);

            // Send message via queue or immediately
            $delay = (int) $this->option('delay');

            if ($this->option('queue') || $delay > 0) {
                $this->info('📨 Queueing template message...');

                $jobId = $this->messageService->queueTemplate($template, $variables, $options, $delay);

                $this->info('✅ Message queued successfully!');
                $this->info("   Job ID: $jobId");

                if ($delay > 0) {
                    $this->info("   Will be sent in $delay seconds");
                }

            } else {
                $this->info('📨 Sending template message...');

                $result = $this->messageService->sendTemplate($template, $variables, $options);

                if ($result['success']) {
                    $this->info('✅ Message sent successfully!');
                    $this->info('   Message ID: ' . ($result['message_id'] ?? 'N/A'));
                    $this->info('   Conversation ID: ' . ($result['conversation_id'] ?? 'N/A'));
                    $this->info('   Sent at: ' . ($result['sent_at'] ?? 'N/A'));
                } else {
                    $this->error('❌ Failed to send message: ' . ($result['error'] ?? 'Unknown error'));

                    return self::FAILURE;
                }
            }

            // Display additional information if verbose
            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->info('🔍 Detailed Information:');
                $routingInfo = $this->inboxManager->getRoutingInfo();
                $this->info('   Account URL: ' . $routingInfo['account_url']);
                $this->info('   Inbox ID: ' . $routingInfo['inbox_id']);
                $this->info('   Available channels: ' . implode(', ', $routingInfo['channels'] ?? []));
            }

        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());

            if ($this->output->isVerbose()) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
