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
    private string $pageUniqueCode;
    private string $version;

    /**
     * @throws \Exception
     */
    public function __construct(array $config, string $pageUniqueCode, string $version = 'v1')
    {
        $this->setToken($config['token']);
        $this->setBaseUrl($config['baseUrl']);
        $this->setPageUniqueCode($pageUniqueCode);
        $this->setVersion($version);
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
     * @param  string  $pageUniqueCode
     * @param  string  $email
     * @param  string  $name
     * @param  string  $mangerEmail
     * @param  string  $version
     *
     * @return \Indeximstudio\src\MultiChat
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public static function MultiChat(
            array $config,
            string  $pageUniqueCode,
            string $email,
            string $name,
            string $mangerEmail = '',
            string $version = 'v1'
    ): MultiChat {
        $multiChat = new MultiChat($config, $pageUniqueCode, $version);

        if (empty($multiChat->customer($email))) {
            $multiChat->createCustomer($email, $name);
        }

        if (empty($multiChat->chat())) {
            $multiChat->createChat($email);
        }

        if (!empty($mangerEmail)){
            // todo получаем менеджера
        }

        return $multiChat;
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
     * @param $timeout
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function createCustomer(string $email, string $name, string $type = 'BUYER', $timeout = 10): array
    {
        $client = new Client();

        $data = [
                'name'      => $name,
                'email'     => $email,
                'type_name' => $type,
        ];

        $response = $client->request(
                'POST',
                $this->getBaseUrl()."/api/{$this->getVersion()}/customers/", [
                'headers' => [
                        'Authorization' => 'Bearer '.$this->getToken(),
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                ],
                'json'    => $data,
                'timeout' => $timeout,
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception("bad response");
        }
        $body          = $response->getBody()->getContents();
        $responseArray = json_decode($body, true);
        if ($responseArray['success'] === true) {
            return $this->customer = $responseArray['data'];
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
    public function customer(string $email, string $type = 'BUYER'): array
    {
        $client = new Client();

        $response = $client->request(
                'GET',
                $this->getBaseUrl()."/api/{$this->getVersion()}/customers/email/{$email}/{$type}", [
                'headers' => [
                        'Authorization' => 'Bearer '.$this->getToken(),
                ],
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception("bad response");
        }
        $body          = $response->getBody()->getContents();
        $responseArray = json_decode($body, true);
        if ($responseArray['success'] === true) {
            return $this->customer = $responseArray['data'];
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
                'timeout' => 10,
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
                'timeout' => 10,
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

        return ! empty($url) ? $url.'/'.$this->customer['id'] : '';
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
}
