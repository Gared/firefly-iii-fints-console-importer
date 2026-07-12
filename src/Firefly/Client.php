<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly;

use Gared\FireflyImporter\Firefly\Exception\FailedException;
use Gared\FireflyImporter\Firefly\Model\AccountType;
use Gared\FireflyImporter\Firefly\Model\CreateTransactionRequest;
use Gared\FireflyImporter\Firefly\Model\FireflyAccount;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client
{
    public function __construct(
        public string $url,
        public string $accessToken,
        public HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<FireflyAccount>
     */
    public function getAccounts(AccountType $accountType): array
    {
        $response = $this->httpClient->request(
            'GET',
            $this->url . '/api/v1/accounts',
            [
                'query' => [
                    'type' => $accountType->value,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            $data = $response->toArray();

            if (array_key_exists('data', $data) === false) {
                throw new FailedException('No data found.');
            }

            if (is_array($data['data']) === false) {
                throw new FailedException('data in response is not an array.');
            }

            $accounts = [];
            foreach ($data['data'] as $accountData) {
                if ($this->validAccountData($accountData) === false) {
                    continue;
                }

                $accounts[] = new FireflyAccount(
                    id: $accountData['id'],
                    iban: $accountData['attributes']['iban'],
                    name: $accountData['attributes']['name'],
                );
            }

            return $accounts;
        }

        $data = $response->toArray(false);
        if (array_key_exists('errors', $data) && is_array($data['errors'])
            && array_key_exists('message', $data) && is_string($data['message'])) {
            throw new FailedException($data['message'], $data['errors']);
        }

        throw new FailedException('failed to get accounts');
    }

    public function postTransactions(CreateTransactionRequest $request): void
    {
        $body = json_encode($request->toArray(), JSON_THROW_ON_ERROR);

        $response = $this->httpClient->request(
            'POST',
            $this->url . '/api/v1/transactions',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'body' => $body,
            ]
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            $response->toArray();

            return;
        }

        $data = $response->toArray(false);

        if (array_key_exists('errors', $data) && is_array($data['errors'])
            && array_key_exists('message', $data) && is_string($data['message'])) {
            throw new FailedException($data['message'], $data['errors']);
        }

        throw new FailedException('Request failed - unable to process response');
    }

    /**
     * @phpstan-assert-if-true array{
     *     id: numeric-string,
     *     attributes: array{
     *         iban: string,
     *         name: string,
     *     }
     * } $accountData
     */
    private function validAccountData(mixed $accountData): bool
    {
        if (is_array($accountData) === false) {
            return false;
        }

        if (array_key_exists('id', $accountData) === false || is_string($accountData['id']) === false || is_numeric($accountData['id']) === false) {
            return false;
        }

        if (array_key_exists('attributes', $accountData) === false || is_array($accountData['attributes']) === false) {
            return false;
        }

        if (array_key_exists('iban', $accountData['attributes']) === false || is_string($accountData['attributes']['iban']) === false) {
            return false;
        }

        if (array_key_exists('name', $accountData['attributes']) === false || is_string($accountData['attributes']['name']) === false) {
            return false;
        }

        return true;
    }
}
