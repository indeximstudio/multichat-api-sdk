<?php

namespace Indeximstudio\MultiChat;

use Exception;
use GuzzleHttp\Client;

class MultiChat
{
    private string $token = '';
    private string $baseUrl = '';
    private array $chat;
    private array $customer;
    private array $manager;
    private array $attachedToCustomerManager;
    private string $pageUniqueCode;
    private string $version;
    private int $timeout;

    /**
     * @throws \Exception
     */
    public function __construct(
        array  $config,
        string $pageUniqueCode,
        string $version = 'v1',
        int    $timeout = 10
    )
    {
        $this->setToken($config['token']);
        $this->setBaseUrl($config['baseUrl']);
        $this->setPageUniqueCode($pageUniqueCode);
        $this->setVersion($version);
        $this->setTimeout($timeout);
    }

    public static function getBearerToken(): ?string
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side headers
            $headers = isset($requestHeaders['Authorization']) ? trim($requestHeaders['Authorization']) : null;
        }

        // Проверяем, что токен передан в формате Bearer
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }


    /**
     * @param  array  $config
     * $config = [
     *    'token' => '',
     *    'baseUrl' => 'MultiChat uri'
     * ],
     * @param  string  $pageUniqueCode
     * @param  string  $customerEmail
     * @param  string  $customerName
     * @param  string  $mangerEmail
     * @param  string  $mangerName
     * @param  string  $version
     *
     * @return \Indeximstudio\MultiChat\MultiChat
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public static function multiChat(
        array  $config,
        string $pageUniqueCode,
        string $customerEmail,
        string $customerName,
        string $mangerEmail = '',
        string $mangerName = '',
        string $attachedToOrderMangerEmail = '',
        string $attachedToOrderMangerName = '',
        string $version = 'v1',
        int    $timeout = 10
    ): MultiChat {
        $multiChat = new self($config, $pageUniqueCode, $version, $timeout);
        if (!empty($customerEmail)) {
            $multiChat->setCustomer($customerEmail, $customerName);
        }
        if (empty($multiChat->chat())) {
            $multiChat->createChat($customerEmail);
        }
        if (!empty($mangerEmail)){
            $multiChat->setManager($mangerEmail, $mangerName);
        }
        if (!empty($attachedToOrderMangerEmail)){
            $multiChat->setAttachedToCustomerManager($attachedToOrderMangerEmail, $attachedToOrderMangerName);
        }

        return $multiChat;
    }

    public static function updateManagersActiveStatus(
        array  $config,
        array  $data,
        string $version = 'v1',
        int    $timeout = 60
    )
    {
        $multiChat = new self($config, '', $version, $timeout);

        $client = new Client();

        $response = $client->request(
            'POST',
            $multiChat->getBaseUrl()."/api/{$multiChat->getVersion()}/managers/change-active-status", [
            'headers' => [
                'Authorization' => 'Bearer ' . $multiChat->getToken(),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'json'    => $data,
            'timeout' => $timeout,
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception("Update manager active status bad response");
        }
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param  string  $token
     *
     * @return void
     * @throws \Exception
     */
    public function setToken(string $token): void
    {
        if (empty($token)) {
            throw new Exception("empty token");
        }
        $this->token = $token;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param  string  $baseUrl
     *
     * @return void
     * @throws \Exception
     */
    public function setBaseUrl(string $baseUrl): void
    {
        if (empty($baseUrl)) {
            throw new Exception("empty BaseUrl");
        }

        $this->baseUrl = $baseUrl;
    }

    /**
     * @param  string  $email
     * @param  string  $name
     * @param  string  $type
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function createChatReader(
        string $email,
        string $name,
        string $type = 'BUYER'
    ): array
    {
        $client = new Client();
        $response = $client->request(
            'POST',
            $this->getBaseUrl()."/api/{$this->getVersion()}/customers/", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'json'    => [
                'name'      => $name,
                'email'     => $email,
                'type_name' => $type,
            ],
            'timeout' => $this->timeout
        ]);
        if ($response->getStatusCode() != 200) {
            throw new Exception("Bad response");
        }
        $body          = $response->getBody()->getContents();
        $responseArray = json_decode($body, true);
        if ($responseArray['success'] === true) {
            return $responseArray['data'];
        }

        return [];
    }

    /**
     * @param  string  $email
     * @param  string  $type  "BUYER" || "MANAGER"
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function getChatReader(string $email, string $type = 'BUYER'): array
    {
        $client = new Client();
        $response = $client->request(
            'GET',
            $this->getBaseUrl()."/api/{$this->getVersion()}/customers/email/$email/$type", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ]
        ]);
        if ($response->getStatusCode() != 200) {
            throw new Exception("Bad response");
        }
        $body          = $response->getBody()->getContents();
        $responseArray = json_decode($body, true);
        if ($responseArray['success'] === true) {
            return $responseArray['data'];
        }

        return [];
    }


    /**
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function chat(): array
    {
        $client = new Client();

        $response = $client->request(
            'GET',
            $this->getBaseUrl()."/api/{$this->getVersion()}/chats/page_unique_code/{$this->getPageUniqueCode()}", [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getToken(),
            ],
            'timeout' => $this->timeout,
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception("checkChat bad response");
        }
        $body          = $response->getBody()->getContents();
        $responseArray = json_decode($body, true);
        if ($responseArray['success'] === true) {
            return $this->chat = $responseArray['data'];
        }

        return [];
    }

    /**
     * @param  string  $email
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function createChat(string $email): array
    {
        $client = new Client();

        $data = [
            'page_unique_code' => $this->getPageUniqueCode(),
            'email'            => $email,
        ];

        $response = $client->request(
            'POST',
            $this->getBaseUrl()."/api/{$this->getVersion()}/chats/", [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getToken(),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'json'    => $data,
            'timeout' => $this->timeout,
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception("createChat bad response");
        }
        $body          = $response->getBody()->getContents();
        $responseArray = json_decode($body, true);
        if ($responseArray['success'] === true) {
            return $this->chat = $responseArray['data'];
        }

        return [];
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function url(): string
    {
        if (empty($this->chat['link'])) {
            throw new Exception("empty link");
        }
        $url = $this->chat['link'] ?? '';
        if (empty($url)) {
            return '';
        }
        if (!empty($this->manager)) {
            $readerId = $this->manager['id'];
        } else {
            $readerId = $this->customer['id'];
        }
        if (!empty($this->attachedToCustomerManager)) {
            $attachedToCustomerManagerId = $this->attachedToCustomerManager['id'];
        }

        return rtrim(
            sprintf(
                '%s/%s/%s',
                $url,
                $readerId,
                $attachedToCustomerManagerId ?? ''
            ),
            '/'
        );
    }

    public function getPageUniqueCode(): string
    {
        return $this->pageUniqueCode;
    }

    public function setPageUniqueCode(string $pageUniqueCode): void
    {
        $this->pageUniqueCode = $pageUniqueCode;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function setTimeout(int $value): void
    {
        $this->timeout = $value;
    }

    /**
     * @param  string  $customerEmail
     * @param  string  $customerName
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function setCustomer(string $customerEmail, string $customerName): void
    {
        $this->customer = $this->getChatReader($customerEmail);
        if (empty($this->customer)) {
            $this->customer = $this->createChatReader($customerEmail, $customerName);
        }
    }

    /**
     * @param  string  $mangerEmail
     * @param  string  $mangerName
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function setManager(string $mangerEmail = '', string $mangerName = ''): void
    {
        $this->manager = $this->getChatReader(
            $mangerEmail,
            'MANAGER'
        );
        if (empty($this->manager)) {
            $this->manager = $this->createChatReader(
                $mangerEmail,
                $mangerName,
                'MANAGER'
            );
        }
    }

    private function setAttachedToCustomerManager(string $mangerEmail = '', string $mangerName = ''): void
    {
        $this->attachedToCustomerManager = $this->getChatReader(
            $mangerEmail,
            'MANAGER'
        );
        if (empty($this->attachedToCustomerManager)) {
            $this->attachedToCustomerManager = $this->createChatReader(
                $mangerEmail,
                $mangerName,
                'MANAGER'
            );
        }
    }
}
