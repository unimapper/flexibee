<?php

namespace UniMapper\Connection;

use Httpful\Request,
    UniMapper\Exceptions\FlexibeeException;

/**
 * Flexibee connection via Httpful library.
 */
class FlexibeeConnection
{

    /** @var string */
    private $url;

    /** @var \Httpful\Request $template Request template */
    private $template;

    /** @var array $responses Logged responses */
    protected $responses = array();

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config)
    {
        $this->url = $config["host"] . "/c/" . $config["company"];
        $this->template = $this->createTemplate($config);
    }

    private function logResponse(\Httpful\Response $response)
    {
        $this->responses[] = $response;
    }

    /**
     * Create request template from given configuration
     *
     * @param string $config Configuration
     *
     * @return \Httpful\Request
     */
    private function createTemplate($config)
    {
        $template = Request::init();
        if (isset($config["user"])) {
            $template->authenticateWith($config["user"], $config["password"])
                ->addOnCurlOption(CURLOPT_SSLVERSION, 3)
                ->withoutStrictSSL()
                ->followRedirects(true);
        }
        return $template;
    }

    /**
     * Get logged responses
     *
     * @return array
     */
    public function getResponses()
    {
        return $this->responses;
    }

    /**
     * Getter for URL
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Send request and get data from response
     *
     * @param string $url URL
     *
     * @return \stdClass | \SimpleXMLElement Output format depends on selected
     *                                       format in URL.
     */
    public function sendGet($url)
    {
        Request::ini($this->template);
        $request = Request::get($url);
        $response = $request->send();
        $this->logResponse($response);

        if ($response->hasErrors()) {
            if (isset($response->body->winstrom->message)) {
                $message = "Error during GET from Flexibee: " .
                    $response->body->winstrom->message .
                    " (" . $url . ")";
            } else {
                $message = "Error during GET from Flexibee" .
                    " (" . $url . ")";
            }
            throw new FlexibeeException($message, $request);
        }

        return $response->body;
    }

    /**
     * Send request as PUT and return response
     *
     * @param string $url     URL
     * @param string $payload Request
     *
     * @return string
     */
    public function sendPut($url, $payload, $contentType = "application/json")
    {
        Request::ini($this->template);
        $request = Request::put($url, $payload, $contentType);
        $response = $request->send();
        $this->logResponse($response);

        if ($response->hasErrors()) {
            preg_match('/.*\"message\":\"(.*)\".*/i', $response->raw_body, $errors);
            if (isset($errors[1])) {
                $message = "Error during PUT to Flexibee: " . $errors[1];
            } else {
                $message = "Error during PUT to Flexibee";
            }

            throw new FlexibeeException($message, $request);
        }

        return $response->body;
    }

}