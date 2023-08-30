<?php

namespace Utopia\Messaging\Adapters\Push;

use Exception;
use Utopia\Messaging\Adapters\Push as PushAdapter;
use Utopia\Messaging\Messages\Push;

class APNS extends PushAdapter
{
    /**
     * @param  string  $authKey
     * @param  string  $authKeyId
     * @param  string  $teamId
     * @param  string  $bundleId
     * @param  string  $endpoint
     * @return void
     */
    public function __construct(
      private string $authKey,
      private string $authKeyId,
      private string $teamId,
      private string $bundleId,
      private string $endpoint
    ) {
    }

    /**
     * Get adapter name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'APNS';
    }

    /**
     * Get max messages per request.
     *
     * @return int
     */
    public function getMaxMessagesPerRequest(): int
    {
        return 1000;
    }

    /**
     * {@inheritdoc}
     *
     * @param  Push  $message
     * @return string
     *
     * @throws Exception
     */
    public function process(Push $message): string
    {
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $message->getTitle(),
                    'body' => $message->getBody(),
                ],
                'badge' => $message->getBadge(),
                'sound' => $message->getSound(),
                'data' => $message->getData(),
            ],
        ];

        // Assuming the 'to' array contains device tokens for the push notification recipients.
        
        // $url = $this->endpoint.'/3/device';
        // $response = $this->request('POST', $url, $headers, \json_encode($payloads));
        // // This example simply returns the last response, adjust as needed
        // return $response;

        return $this->notify($message->getTo(), $payload);
    }

    private function notify(array $to, array $payload)
    {
      $headers = [
        'authorization: bearer '.$this->generateJwt(),
        'apns-topic: '.$this->bundleId,
    ];

      $ch = curl_init();

      curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

      curl_setopt_array($ch, array(
        CURLOPT_PORT => 443,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => TRUE,
        CURLOPT_POSTFIELDS => \json_encode($payload),
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => TRUE
      ));

      $response = '';

      foreach($to as $token) {
        curl_setopt($ch, CURLOPT_URL, $this->endpoint.'/3/device/'.$token);

        $response = curl_exec($ch);
      }

      curl_close($ch);

      $response = $this->formatResponse($response);

      var_dump($response);
      die;
      return $response;
    }

    private function formatResponse(string $response):array
    {
      $filtered = array_filter(
        explode("\r\n", $response),
        function($value) {
          return !empty($value);
        }
      );

      $result = [];

      foreach($filtered as $value) {
        if(str_contains($value, 'HTTP')) {
          $result['status'] = trim(str_replace('HTTP/2 ', '', $value));
          continue;
        }

        $parts = explode(':', trim($value));

        $result[$parts[0]] = $parts[1];
      }

      var_dump($result);
      die;

      return $result;
    }

    /**
     * Generate JWT.
     *
     * @return string
     *
     * @throws Exception
     */
    private function generateJwt(): string
    {
        $header = json_encode(['alg' => 'ES256', 'kid' => $this->authKeyId]);
        $claims = json_encode([
            'iss' => $this->teamId,
            'iat' => time(),
        ]);

        // Replaces URL sensitive characters that could be the result of base64 encoding.
        // Replace to _ to avoid any special handling.
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlClaims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claims));

        if (! $this->authKey) {
            throw new \Exception('Invalid private key');
        }

        $signature = '';
        $success = openssl_sign("$base64UrlHeader.$base64UrlClaims", $signature, $this->authKey, OPENSSL_ALGO_SHA256);

        if (! $success) {
            throw new \Exception('Failed to sign JWT');
        }

        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return "$base64UrlHeader.$base64UrlClaims.$base64UrlSignature";
    }
}
