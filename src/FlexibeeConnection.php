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
    private $baseUrl;

    /** @var \Httpful\Request $template Request template */
    private $template;

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config)
    {
        $this->baseUrl = $config["host"] . "/c/" . $config["company"];
        $this->template = $this->createTemplate($config);
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
     * Getter for URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Send request and get data from response
     *
     * @param string $url URL
     *
     * @return \stdClass | \SimpleXMLElement Output format depends on selected
     *                                       format in URL.
     */
    public function get($url)
    {
        $url = $this->baseUrl . "/" . $url;
        Request::ini($this->template);
        $request = Request::get($url);
        $response = $request->send();

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

        if (isset($response->body->winstrom)) {
            return $response->body->winstrom;
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
    public function put($url, $payload, $contentType = "application/json")
    {
        $url = $this->baseUrl . "/" . $url;
        Request::ini($this->template);
        $request = Request::put($url, $payload, $contentType);
        $response = $request->send();

        if ($response->hasErrors()) {
            preg_match('/.*\"message\":\"(.*)\".*/i', $response->raw_body, $errors);
            if (isset($errors[1])) {
                $message = "Error during PUT to Flexibee: " . $errors[1];
            } else {
                $message = "Error during PUT to Flexibee";
            }

            throw new FlexibeeException($message, $request);
        }

        if (isset($response->body->winstrom)) {
            return $response->body->winstrom;
        }

        // Check if request failed
        if (isset($response->body->success)
            && $response->body->success === "false") {

            if (isset($response->body->results[0]->errors[0])) {

                $errorDetails = $response->body->results[0]->errors[0];
                $error = "";

                if (isset($errorDetails->message)) {
                    $error .= " MESSAGE: {$errorDetails->message}";
                }
                if (isset($errorDetails->for)) {
                    $error .= " FOR: {$errorDetails->for}";
                }
                if (isset($errorDetails->value)) {
                    $error .= " VALUE: {$errorDetails->value}";
                }
                if (isset($errorDetails->code)) {
                    $error .= " CODE: {$errorDetails->code}";
                }
            }

            if (isset($error)) {
                throw new FlexibeeException("Flexibee error: {$error}");
            }

            if (isset($response->body->message)) {
                throw new FlexibeeException("Flexibee error: " . $response->body->message, $request);
            }

            throw new FlexibeeException("An unknown flexibee error occurred", $request);
        }

        return $response->body;
    }

}