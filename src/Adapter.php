<?php

namespace UniMapper\Flexibee;

use UniMapper\Association;
use UniMapper\Adapter\IQuery;

class Adapter extends \UniMapper\Adapter
{

    const CONTENT_JSON = "application/json";
    const CONTENT_XML = "application/xml";

    const METHOD_GET = "get";
    const METHOD_PUT = "put";
    const METHOD_POST = "post";
    const METHOD_DELETE = "delete";

    /** @var bool */
    public static $likeWithSimilar = true;
    public static $codeAsId = true;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $user;

    /** @var string */
    private $password;

    /** @var integer */
    private $sslVersion;

    /** @var string */
    private $authUser;

    public function __construct(array $config)
    {
        parent::__construct(new Adapter\Mapping);

        $this->baseUrl = $config["host"] . "/c/" . $config["company"];
        $this->sslVersion = isset($config["ssl_version"]) ? (int) $config["ssl_version"] : null;
        $this->user = isset($config["user"]) ? $config["user"] : null;
        if ($this->user !== null) {

            if (isset($config["password"])) {
                $this->password =  $config["password"];
            } else {
                throw new \Exception("Password is required if user set!");
            }
        }
    }

    protected function send(
        $url,
        $method = self::METHOD_GET,
        $contentType = self::CONTENT_JSON,
        $content = null,
        array $headers = []
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($this->sslVersion !== null) {
            curl_setopt($ch, CURLOPT_SSLVERSION, $this->sslVersion);
        }

        // Authentization
        if ($this->user !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);
        }

        // Set content type
        $headers[] = "Content-type: " . $contentType;

        // Set content
        if ($content) {

            if ($contentType === self::CONTENT_JSON) {
                $content = json_encode(["winstrom" => $content]);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }

        // Authorization
        if ($this->authUser !== null) {
            $headers[] = "X-FlexiBee-Authorization: " . $this->authUser;
        }

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . "/" . $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception\AdapterException(curl_error($ch), curl_getinfo($ch));
        }

        // Parse response
        switch ($contentType) {
        case self::CONTENT_JSON:
            $response = json_decode($response);
            break;
        case self::CONTENT_XML:
            $response = simplexml_load_string($response);
            break;
        }

        // Get response body root automatically
        if (isset($response->winstrom)) {
            $response = $response->winstrom;
        }

        // Detect errors in result
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200
            && curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201
        ) {
            throw new Exception\AdapterException(
                "An unknown Flexibee error occurred!",
                curl_getinfo($ch),
                $response
            );
        }

        curl_close($ch);

        return $response;
    }

    public function setAuthUser($name)
    {
        $this->authUser = (string) $name;
    }

    public function createDelete($evidence)
    {
        $query = new Query($evidence, Query::PUT, [$evidence => ["@action" => "delete"]]);
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
                                    $result[$index]->{$association->getPropertyName()}[] = $relation->{$association->getReferencingKey()}[0];// 'a' or 'b'
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

                        $result[$index]->{$association->getPropertyName()} = $item->{$association->getReferencingKey()};
                    } elseif ($association instanceof Association\ManyToOne ) {
                        // N:1

                        $result[$index]->{$association->getPropertyName()} = $item->{$association->getReferencingKey()};
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
                                    $result[$index]->{$propertyName}[] = $relation->{$association->getReferencingKey()}[0];// 'a' or 'b'
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

                        $result[$index]->{$propertyName} = $item->{$association->getReferencingKey()};
                    } elseif ($association instanceof Association\ManyToOne) {
                        // N:1

                        $result[$index]->{$propertyName} = $item->{$association->getReferencingKey()};
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
            throw new \Exception("Only custom relations can be modified!");
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
            throw new \Exception(
                "Custom relation delete not implemented!"
            );
        }

        return $query;
    }

    public function createInsert($evidence, array $values)
    {
        // Support for multi-insert
        if ($values === array_values($values)) {
            foreach ($values as $index => $value) {
                $values[$index]["@update"] = "fail";
            }
        } else {
            $values["@update"] = "fail";
        }

        $query = new Query(
            $evidence,
            Query::PUT,
            [$evidence => $values]
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
        // Support for multi-update
        if ($values === array_values($values)) {
            foreach ($values as $index => $value) {
                $values[$index]["@create"] = "fail";
            }
        } else {
            $values["@create"] = "fail";
        }

        $query = new Query(
            $evidence,
            Query::PUT,
            [$evidence => $values]
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
        $this->send($url, self::METHOD_DELETE);
        return true;
    }

    /**
     * Send request and get data from response
     *
     * @param string $url
     * @param string $contentType
     *
     * @return mixed
     */
    public function get($url, $contentType = self::CONTENT_JSON)
    {
        $result = $this->send($url, self::METHOD_GET, $contentType);

        // Replace id with code if enabled
        if ($contentType === self::CONTENT_JSON
            && self::$codeAsId
            && (is_array($result)
            || is_object($result))
        ) {

            foreach ($result as $name => $value) {

                if (is_array($value)) {

                    foreach ($value as $index => $item) {
                        $result->{$name}[$index] = $this->_replaceExternalIds($item);
                    }
                } else {
                     $result->{$name} = $this->_replaceExternalIds($value);
                }
            }
        }

        return $result;
    }

    /**
     * Sends PUT request
     *
     * @param string $url         URL
     * @param string $content     Request body
     * @param string $contentType
     * @param array  $headers
     *
     * @return mixed
     */
    public function put($url, $content, $contentType = self::CONTENT_JSON, array $headers = [])
    {
        return $this->send($url, self::METHOD_PUT, $contentType, $content, $headers);
    }

    /**
     * Sends POST request
     *
     * @param type $url
     * @param type $content
     * @param type $contentType
     * @param array $headers
     */
    public function post($url, $content, $contentType, array $headers = [])
    {
        return $this->send($url, self::METHOD_POST, $contentType, $content, $headers);
    }

    /**
     * Replace id value with 'code:...' from external-ids automatically
     *
     * @param mixed $data
     *
     * @return mixed
     */
    private function _replaceExternalIds($data)
    {
        if (is_object($data) && isset($data->{"external-ids"}) && isset($data->id)) {

            foreach ($data->{"external-ids"} as $externalId) {
                if (substr($externalId, 0, 5) === "code:") {
                    $data->id = $externalId;
                    break;
                }
            }
        }

        return $data;
    }

}