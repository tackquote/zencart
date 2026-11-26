<?php
/**
 * Thin HTTP client for the TackQuote API, using cURL.
 *
 * Mirrors TackApiClient in the TackQuote PrestaShop module
 * (integrations/prestashop/modules/tackquotes/classes/TackApiClient.php) and
 * Tack_Api_Client in the WooCommerce plugin: same headers, same auth scheme
 * (Bearer + X-Api-Key), same JSON contract.
 *
 * Zen Cart ships no class autoloader guarantee across every install/version,
 * so this file is a plain, dependency-free PHP class deliberately included
 * with a direct `require_once` (see ajax_tack_quote_request.php) rather than
 * relying on Zen Cart's auto_loaders mechanism.
 */

if (!defined('DIR_WS_INCLUDES') && !defined('TACK_QUOTE_STANDALONE_TEST')) {
    // Loaded outside of a Zen Cart bootstrap — refuse to run silently.
    exit('TackApiClient must be loaded after includes/application_top.php.');
}

class TackApiClient
{
    /** @var string */
    protected $baseUrl;

    /** @var string */
    protected $apiKey;

    /**
     * @param string $baseUrl e.g. https://api.tackquote.com/v1 (no trailing slash).
     * @param string $apiKey  Tenant's TackQuote API key.
     */
    public function __construct($baseUrl, $apiKey)
    {
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->apiKey = (string) $apiKey;
    }

    /** Outcomes of checkConnection(). */
    const CONNECTION_OK = 'ok';
    const CONNECTION_KEY_REJECTED = 'key_rejected';
    const CONNECTION_KEY_UNCHECKED = 'key_unchecked';
    const CONNECTION_FAILED = 'failed';

    /**
     * Perform an authenticated request against the TackQuote API.
     *
     * @param string     $method HTTP method.
     * @param string     $path   Path beginning with '/'.
     * @param array|null $body   Optional JSON-encodable body.
     *
     * @return array|string Decoded response array on 2xx, or an error message string.
     */
    public function request($method, $path, $body = null)
    {
        if ($this->apiKey === '') {
            return 'No TackQuote API key configured.';
        }
        if (!function_exists('curl_init')) {
            return 'PHP cURL extension is not available on this server.';
        }

        $result = $this->exchange($method, $path, $body);

        return $result['error'] !== null ? $result['error'] : $result['data'];
    }

    /**
     * One HTTP exchange, keeping the status code that request() folds into a string.
     *
     * `status` is 0 when no HTTP answer arrived at all (DNS, refused, TLS, timeout,
     * or no cURL extension). `error` is null exactly when the status is 2xx.
     *
     * @return array{status: int, data: array, error: string|null}
     */
    protected function exchange($method, $path, $body = null)
    {
        if (!function_exists('curl_init')) {
            return array('status' => 0, 'data' => array(), 'error' => 'PHP cURL extension is not available on this server.');
        }

        $url = $this->baseUrl . $path;

        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: TackQuote-ZenCart/1.0.0',
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return array('status' => 0, 'data' => array(), 'error' => sprintf('Connection error: %s', $error));
        }

        $data = json_decode((string) $response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($data) && isset($data['message'])
                ? (is_array($data['message']) ? implode(', ', $data['message']) : (string) $data['message'])
                : sprintf('TackQuote API returned HTTP %d.', $httpCode);

            return array('status' => $httpCode, 'data' => is_array($data) ? $data : array(), 'error' => $message);
        }

        return array('status' => $httpCode, 'data' => is_array($data) ? $data : array(), 'error' => null);
    }

    /**
     * Connectivity + API-key check against `GET /integrations/zencart/ping`, which
     * TackQuote authenticates with the store's API key. Any 2xx is CONNECTION_OK: a
     * lapsed TackQuote account still answers 200 (with a reduced `capabilities` list),
     * and its key is valid.
     *
     * 401/403 mean TackQuote rejected the key: CONNECTION_KEY_REJECTED, no fallback.
     * This used to fall back to `GET /health` on ANY ping failure, and /health answers
     * 200 without a key, so a wrong or revoked key reported success.
     *
     * `/health` is tried only when the ping failure says nothing about the key: no HTTP
     * answer (network error, timeout), 404 (a TackQuote API older than the route) or
     * 5xx. If /health then answers 2xx the result is CONNECTION_KEY_UNCHECKED, never OK.
     * Every other status (400, 429, ...) is CONNECTION_FAILED with TackQuote's message.
     *
     * @return array{result: string, message: string} `message` is the ping's own error
     *                                                text ('' on success).
     */
    public function checkConnection()
    {
        if ($this->apiKey === '') {
            return array('result' => self::CONNECTION_FAILED, 'message' => 'No TackQuote API key configured.');
        }

        $ping = $this->exchange('GET', '/integrations/zencart/ping');
        $status = $ping['status'];

        if ($ping['error'] === null) {
            return array('result' => self::CONNECTION_OK, 'message' => '');
        }

        if ($status === 401 || $status === 403) {
            return array('result' => self::CONNECTION_KEY_REJECTED, 'message' => (string) $ping['error']);
        }

        if ($status === 0 || $status === 404 || $status >= 500) {
            $health = $this->exchange('GET', '/health');
            if ($health['error'] === null) {
                return array('result' => self::CONNECTION_KEY_UNCHECKED, 'message' => (string) $ping['error']);
            }
        }

        return array('result' => self::CONNECTION_FAILED, 'message' => (string) $ping['error']);
    }

    /**
     * @return true|string True only when TackQuote accepted the API key; otherwise a
     *                     message. A reachable API with an unchecked key is NOT true.
     */
    public function testConnection()
    {
        $check = $this->checkConnection();

        switch ($check['result']) {
            case self::CONNECTION_OK:
                return true;
            case self::CONNECTION_KEY_REJECTED:
                return 'TackQuote rejected this API key. Check it in TackQuote under Settings > Developer > API keys.';
            case self::CONNECTION_KEY_UNCHECKED:
                return 'TackQuote is reachable, but the API key could not be checked.';
            default:
                return $check['message'];
        }
    }

    /**
     * Create a quote request from a product page.
     *
     * Calls POST /integrations/zencart/quote-requests — see
     * apps/api/src/modules/integrations/zencart/zencart-plugin.controller.ts.
     *
     * @param array $payload {buyerEmail, note, source, lineItems:[{sku,name,quantity,unitPrice,externalProductId}]}.
     *
     * @return array|string Decoded response (includes id/quoteNumber/portalUrl), or an error message.
     */
    public function createQuoteRequest($payload)
    {
        return $this->request('POST', '/integrations/zencart/quote-requests', $payload);
    }
}
