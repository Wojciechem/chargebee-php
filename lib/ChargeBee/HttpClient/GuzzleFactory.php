<?php

namespace ChargeBee\ChargeBee\HttpClient;

use ChargeBee\ChargeBee;
use ChargeBee\ChargeBee\Request;
use ChargeBee\ChargeBee\Version;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class GuzzleFactory implements HttpClientFactory
{
    private $options;
    private $connectTimeoutInSecs;
    private $requestTimeoutInSecs;

    /**
     * @param array $options
     * @param float $connectTimeoutInSecs
     * @param float $requestTimeoutInSecs
     */
    public function __construct($connectTimeoutInSecs, $requestTimeoutInSecs, $options = [])
    {
        $this->connectTimeoutInSecs = $connectTimeoutInSecs;
        $this->requestTimeoutInSecs = $requestTimeoutInSecs;
        $this->options = $options;
    }

    /**
     * @return ClientInterface|Client
     */
    public function createClient()
    {
        return new Client(
            array_merge(
                [
                    'allow_redirects' => true,
                    'http_errors' => false,
                    'connect_timeout' => $this->connectTimeoutInSecs,
                    'timeout' => $this->requestTimeoutInSecs,
                    // Specifying a CA bundle results in the following error when running in Google App Engine:
                    // "Unsupported SSL context options are set. The following options are present, but have been ignored: allow_self_signed, cafile"
                    // https://cloud.google.com/appengine/docs/php/outbound-requests#secure_connections_and_https
                    // TODO: extract parameter
                    'verify' => ChargeBee::getVerifyCaCerts() && !self::isAppEngine() ? ChargeBee::getCaCertPath() : false
                ],
                $this->options
            )
        );
    }

    /**
     * @throws Exception
     * @return RequestInterface
     */
    public function createRequest($meth, $headers, $env, $url, $params)
    {
        if (!in_array($meth, [Request::GET, Request::POST])) {
            throw new Exception("Invalid http method $meth");
        }

        $userAgent = "Chargebee-PHP-Client" . " v" . Version::VERSION;
        $httpHeaders = array_merge(
            $headers,
            [
                'Accept' => 'application/json',
                'User-Agent' => $userAgent,
                'Lang-Version' => phpversion(),
                'OS-Version' => PHP_OS,
                'Authorization' => 'Basic ' . \base64_encode($env->getApiKey() . ':')
            ]
        );
        $body = null;

        $uri = new Uri($url);

        if ($meth == Request::GET) {
            if (count($params) > 0) {
                $query = \http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
                $uri = $uri->withQuery($query);
            }
        }

        if ($meth == Request::POST) {
            $body = \http_build_query($params, '', '&');
            $httpHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return new \GuzzleHttp\Psr7\Request($meth, $uri, $httpHeaders, $body);
    }

    /**
     * Recommended way to check if script is running in Google App Engine:
     * https://github.com/google/google-api-php-client/blob/master/src/Google/Client.php#L799
     *
     * @return bool Returns true if running in Google App Engine
     */
    public static function isAppEngine()
    {
        return (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Google App Engine') !== false);
    }
}
