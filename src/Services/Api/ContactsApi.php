<?php

namespace BassamShoukry\LaravelChatwoot\Services\Api;

class ContactsApi extends BaseApiService
{
    /**
     * Get all contacts for the account.
     */
    public function list(array $params = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Get a specific contact.
     */
    public function get(int $contactId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$contactId";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Create a new contact.
     */
    public function create(array $contactData): array
    {
        $this->validateRequiredFields($contactData, ['name']);

        $allowedFields = [
            'name', 'email', 'phone_number', 'identifier',
            'custom_attributes', 'additional_attributes', 'avatar_url',
        ];

        $filteredData = $this->filterAllowedFields($contactData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts";

        return $this->makeRequest('POST', $endpoint, $filteredData);
    }

    /**
     * Update an existing contact.
     */
    public function update(int $contactId, array $contactData): array
    {
        $allowedFields = [
            'name', 'email', 'phone_number', 'identifier',
            'custom_attributes', 'additional_attributes', 'avatar_url',
        ];

        $filteredData = $this->filterAllowedFields($contactData, $allowedFields);

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$contactId";

        return $this->makeRequest('PATCH', $endpoint, $filteredData);
    }

    /**
     * Delete a contact.
     */
    public function delete(int $contactId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$contactId";

        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Search contacts.
     */
    public function search(string $query, array $params = []): array
    {
        $params['q'] = $query;

        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/search";

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Get contact conversations.
     */
    public function getConversations(int $contactId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$contactId/conversations";

        return $this->makeRequest('GET', $endpoint);
    }

    /**
     * Find or create contact by identifier.
     */
    public function findOrCreate(array $contactData): array
    {
        $this->validateRequiredFields($contactData, ['identifier']);

        // Try to search for existing contact first
        try {
            $searchResults = $this->search($contactData['identifier']);

            if (! empty($searchResults['payload']) && is_array($searchResults['payload'])) {
                foreach ($searchResults['payload'] as $contact) {
                    if ($contact['identifier'] === $contactData['identifier']) {
                        return $contact; // Return existing contact
                    }
                }
            }
        } catch (\Exception $e) {
            // Continue to create if search fails
        }

        // Create new contact if not found
        return $this->create($contactData);
    }

    /**
     * Merge two contacts.
     */
    public function merge(int $baseContactId, int $mergeContactId): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$baseContactId/merge";

        $data = ['contact_id' => $mergeContactId];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Get contact's custom attributes.
     */
    public function getCustomAttributes(int $contactId): array
    {
        $contact = $this->get($contactId);

        return $contact['custom_attributes'] ?? [];
    }

    /**
     * Update contact's custom attributes.
     */
    public function updateCustomAttributes(int $contactId, array $customAttributes): array
    {
        return $this->update($contactId, ['custom_attributes' => $customAttributes]);
    }

    /**
     * Add labels to contact.
     */
    public function addLabels(int $contactId, array $labels): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$contactId/labels";

        $data = ['labels' => $labels];

        return $this->makeRequest('POST', $endpoint, $data);
    }

    /**
     * Remove labels from contact.
     */
    public function removeLabels(int $contactId, array $labels): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts/$contactId/labels";

        $data = ['labels' => $labels];

        return $this->makeRequest('DELETE', $endpoint, $data);
    }

    /**
     * Get paginated contacts with filtering.
     */
    public function getPaginated(int $page = 1, int $perPage = 25, array $filters = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts";

        return $this->paginate($endpoint, $page, $perPage, $filters);
    }

    /**
     * Get all contacts (handles pagination automatically).
     */
    public function getAll(array $filters = []): array
    {
        $account = $this->getCurrentAccount();
        $endpoint = "accounts/{$account['account_key']}/contacts";

        return $this->fetchAll($endpoint, $filters);
    }

    /**
     * Bulk update contacts.
     */
    public function bulkUpdate(array $updates): array
    {
        $results = [];

        foreach ($updates as $update) {
            if (! isset($update['id'])) {
                $results[] = [
                    'success' => false,
                    'error'   => 'Contact ID required for update',
                    'data'    => $update,
                ];

                continue;
            }

            try {
                $contactId = $update['id'];
                unset($update['id']);

                $result = $this->update($contactId, $update);
                $results[] = [
                    'success'    => true,
                    'contact_id' => $contactId,
                    'data'       => $result,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'success'    => false,
                    'contact_id' => $update['id'] ?? null,
                    'error'      => $e->getMessage(),
                    'data'       => $update,
                ];
            }
        }

        return $results;
    }
}
