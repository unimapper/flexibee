<?php

namespace UniMapper\Flexibee;

use Httpful\Request,
    UniMapper\Association,
    UniMapper\Adapter\IQuery;

class Adapter extends \UniMapper\Adapter
{

    /** @var Adapter\Mapper */
    private $mapper;

    /** @var string */
    private $baseUrl;

    /** @var \Httpful\Request $template Request template */
    private $template;

    public function __construct(array $config)
    {
        $this->baseUrl = $config["host"] . "/c/" . $config["company"];
        $this->mapper = new Adapter\Mapper;

        $this->template = Request::init();
        if (isset($config["user"])) {
            $this->template->authenticateWith($config["user"], $config["password"])
                ->addOnCurlOption(CURLOPT_SSLVERSION, 3)
                ->withoutStrictSSL()
                ->followRedirects(true);
        }
    }

    public function setAuthUser($username)
    {
        $this->template->addHeader("X-FlexiBee-Authorization", $username);
    }

    public function createDelete($evidence)
    {
        $query = new Query($evidence, Query::PUT, ["@action" => "delete"]);
        $query->resultCallback = function ($result) {
            return (int) $result->stats->deleted;
        };
        return $query;
    }

    public function createDeleteOne($evidence, $column, $primaryValue)
    {
        $query = new Query($evidence, Query::DELETE);
        $query->id = $primaryValue;
        return $query;
    }

    public function createSelectOne($evidence, $column, $primaryValue)
    {
        $query = new Query($evidence);
        $query->id = $primaryValue;

        $query->resultCallback = function ($result, Query $query) {

            $result = $result->{$query->evidence};
            if (count($result) === 0) {
                return false;
            }

            // Merge associations results for mapping compatibility
            foreach ($result as $index => $item) {

                foreach ($query->associations as $association) {

                    if ($association instanceof Association\ManyToMany) {
                        // M:N

                        $result[$index]->{$association->getPropertyName()} = [];

                        if ($association->getJoinKey() === "vazby") {

                            foreach ($item->vazby as $relation) {

                                if ($relation->typVazbyK === $association->getJoinResource()) { // eg. typVazbyDokl.obchod_zaloha_hla
                                    $result[$index]->{$association->getPropertyName()}[] = $relation->{$association->getReferenceKey()}[0];// 'a' or 'b'
                                }
                            }
                        } elseif ($association->getJoinKey() === "uzivatelske-vazby") {

                            foreach ($item->{"uzivatelske-vazby"} as $relation) {

                                if ($relation->vazbaTyp === $association->getJoinResource()) { // eg. 'code:MY_CUSTOM_ID'
                                    $result[$index]->{$association->getPropertyName()}[] = $relation->object[0];
                                }
                            }
                        }
                    } elseif ($association instanceof Association\OneToOne) {
                        // 1:1

                        $result[$index]->{$association->getPropertyName()} = $item->{$association->getForeignKey()};
                    } elseif ($association instanceof Association\ManyToOne ) {
                        // N:1

                        $result[$index]->{$association->getPropertyName()} = $item->{$association->getReferenceKey()};
                    }
                }
            }

            return $result[0];
        };

        return $query;
    }

    public function createSelect($evidence, array $selection = [], array $orderBy = [], $limit = 0, $offset = 0)
    {
        $query = new Query($evidence);

        $query->parameters["start"] = (int) $offset;
        $query->parameters["limit"] = (int) $limit;

        foreach ($orderBy as $name => $direction) {

            if ($direction === \UniMapper\Query\Select::ASC) {
                $direction = "A";
            } else {
                $direction = "D";
            }
            $query->parameters["order"] = $name . "@" . $direction;
            break; // @todo multiple not supported yet
        }

        if ($selection) {
            $query->parameters["detail"] = "custom:" . implode(",", $this->_escapeSelection($selection));
        }

        $query->resultCallback = function ($result, Query $query) {

            $result = $result->{$query->evidence};
            if (count($result) === 0) {
                return false;
            }

            // Merge associations results for mapping compatibility
            foreach ($result as $index => $item) {

                foreach ($query->associations as $association) {

                    $propertyName = $association->getPropertyName();

                    if ($association instanceof Association\ManyToMany) {
                        // M:N

                        $result[$index]->{$propertyName} = [];

                        if ($association->getJoinKey() === "vazby") {

                            foreach ($item->vazby as $relation) {

                                if ($relation->typVazbyK === $association->getJoinResource()) { // eg. typVazbyDokl.obchod_zaloha_hla
                                    $result[$index]->{$propertyName}[] = $relation->{$association->getReferenceKey()}[0];// 'a' or 'b'
                                }
                            }
                        } elseif ($association->getJoinKey() === "uzivatelske-vazby") {

                            foreach ($item->{"uzivatelske-vazby"} as $relation) {

                                if ($relation->vazbaTyp === $association->getJoinResource()) { // eg. 'code:MY_CUSTOM_ID'
                                    $result[$index]->{$propertyName}[] = $relation->object[0];
                                }
                            }
                        }
                    } elseif ($association instanceof Association\OneToOne) {
                        // 1:1

                        $result[$index]->{$propertyName} = $item->{$association->getForeignKey()};
                    } elseif ($association instanceof Association\ManyToOne) {
                        // N:1

                        $result[$index]->{$propertyName} = $item->{$association->getReferenceKey()};
                    }
                }
            }

            return $result;
        };

        return $query;
    }

    public function createCount($evidence)
    {
        $query = new Query($evidence);
        $query->parameters["detail"] = "id";
        $query->parameters["add-row-count"] = "true";
        $query->resultCallback = function ($result) {
            return $result->{"@rowCount"};
        };
        return $query;
    }

    public function createModifyManyToMany(Association\ManyToMany $association, $primaryValue, array $refKeys, $action = self::ASSOC_ADD)
    {
        if ($association->getJoinKey() !== "uzivatelske-vazby") {
            throw new \UniMapper\Exception\AdapterException("Only custom relations can be modified!");
        }

        if ($action === self::ASSOC_ADD) {

            $values = [];
            foreach ($refKeys as $refkey) {
                $values[] = [
                    "id" => $primaryValue,
                    "uzivatelske-vazby" => [
                        "uzivatelska-vazba" => [
                            "evidenceType" => $association->getTargetResource(),
                            "object" => $refkey,
                            "vazbaTyp" => $association->getJoinResource()
                        ]
                    ]
                ];
            }

            $query = $this->createInsert(
                $association->getSourceResource(),
                $values
            );
        } elseif ($action === self::ASSOC_REMOVE) {
            throw new \UniMapper\Exception\AdapterException(
                "Custom relation delete not implemented!"
            );
        }

        return $query;
    }

    public function createInsert($evidence, array $values)
    {
        $query = new Query(
            $evidence,
            Query::PUT,
            ["@update" => "fail", $evidence => $values]
        );
        $query->resultCallback = function ($result) {

            foreach ($result->results as $item) {

                if (isset($item->code)) {
                    return "code:" . $item->code;
                } else {
                    return $item->id;
                }
            }
        };
        return $query;
    }

    public function createUpdate($evidence, array $values)
    {
        $query = new Query(
            $evidence,
            Query::PUT,
            ["@create" => "fail", $evidence => $values]
        );
        $query->resultCallback = function ($result) {
            return (int) $result->stats->updated;
        };
        return $query;
    }

    public function createUpdateOne($evidence, $column, $primaryValue, array $values)
    {
        $values[$column] = $primaryValue;

        $query = $this->createUpdate($evidence, $values);
        $query->id = $primaryValue;
        $query->resultCallback = function ($result) {
            return $result->stats->updated === "0" ? false : true;
        };

        return $query;
    }

    protected function onExecute(IQuery $query)
    {
        if ($query->method === Query::PUT) {

            if ($query->filter) {
                $query->data[$query->evidence]["@filter"] = $query->filter;
            }
            $result = $this->put($query->getRaw(), $query->data);
        } elseif ($query->method === Query::GET) {
            $result = $this->get($query->getRaw());
        } elseif ($query->method === Query::DELETE) {
            $result = $this->delete($query->getRaw());
        }

        $callback = $query->resultCallback;
        if ($callback) {
            return $callback($result, $query);
        }

        return $result;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Escape properties with @ char (polozky@removeAll), @showAs, @ref ...
     *
     * @param array $properties
     *
     * @return array
     */
    private function _escapeSelection(array $properties)
    {
        foreach ($properties as $index => $item) {

            if ($this->_endsWith($item, "@removeAll")) {
                $properties[$index] = substr($item, 0, -10);
            } elseif ($this->_endsWith($item, "@showAs") || $this->_endsWith($item, "@action")) {
                $properties[$index] = substr($item, 0, -7);
            } elseif ($this->_endsWith($item, "@ref")) {
                $properties[$index] = substr($item, 0, -4);
            }
        }
        return $properties;
    }

    private function _endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

    public function getMapper()
    {
        return $this->mapper;
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

    public function delete($url)
    {
        $url = $this->baseUrl . "/" . $url;
        Request::ini($this->template);
        $request = Request::delete($url);
        $response = $request->send();

        if ($response->code !== 200) {
            return false;
        }
        return true;
    }

    /**
     * Send request and get data from response
     *
     * @param string $url URL
     *
     * @return \stdClass|\SimpleXMLElement Output format depends on selected
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
            throw new Exception($message, $request);
        }

        if (isset($response->body->winstrom)) {
            $result = $response->body->winstrom;
        } else {
            $result = $response->body;
        }

        // Check if request failed
        if (isset($result->success) && $result->success === "false") {
            throw new Exception($result->message, $request);
        }
        return $result;
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

        if ($contentType === "application/json") {
            $payload = json_encode(array("flexibee" => $payload));
        }

        $request = Request::put($url, $payload, $contentType);
        $response = $request->send();

        if ($response->hasErrors()) {
            preg_match('/.*\"message\":\"(.*)\".*/i', $response->raw_body, $errors);
            if (isset($errors[1])) {
                $message = "Error during PUT to Flexibee: " . $errors[1];
            } else {
                $message = "Error during PUT to Flexibee";
            }

            throw new Exception($message, $request);
        }

        if (isset($response->body->winstrom)) {
            return $response->body->winstrom;
        }

        // Check if request failed
        if (isset($response->body->success)
            && $response->body->success === "false"
        ) {

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
                throw new Exception("Flexibee error: {$error}");
            }

            if (isset($response->body->message)) {
                throw new Exception("Flexibee error: " . $response->body->message, $request);
            }

            throw new Exception("An unknown flexibee error occurred", $request);
        }

        return $response->body;
    }

}