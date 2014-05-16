<?php

namespace UniMapper\Mapper;

use UniMapper\Reflection,
    UniMapper\Connection\FlexibeeConnection,
    UniMapper\Exceptions\MapperException;

/**
 * Flexibee mapper can be generally used to communicate between repository and
 * Flexibee REST API.
 */
class FlexibeeMapper extends \UniMapper\Mapper
{

    const DATETIME_FORMAT = "Y-m-d\TH:i:sP";

    /** @var \DibiConnection $connection Dibi connection */
    protected $connection;

    public function __construct($name, FlexibeeConnection $connection)
    {
        parent::__construct($name);
        $this->connection = $connection;
    }

    protected function unmapValue($value)
    {
        $value = parent::unmapValue($value);
        if ($value === null) {
            $value = "";
        } elseif ($value instanceof \DateTime) {
            $value = $value->format(self::DATETIME_FORMAT);
        }
        return $value;
    }

    /**
     * Delete
     *
     * @param \UniMapper\Query\Delete $query Query
     *
     * @return mixed
     */
    public function delete(\UniMapper\Query\Delete $query)
    {
        $resource = $this->getResource($query->entityReflection);

        $xml = new \SimpleXMLElement('<winstrom version="1.0" />');
        $xmlResource = $xml->addChild($resource);
        $xmlResource->addAttribute(
            "filter",
            $this->convertConditions(
                $this->translateConditions($query->entityReflection, $query->conditions)
            )
        );
        $xmlResource->addAttribute("action", "delete");

        $this->connection->put(
            rawurlencode($resource) . ".xml",
            $xml->asXML(),
            "application/xml"
        );

        return true;
    }

    /**
     * Use 'code:' identifier as primary identifier of entities
     *
     * @param mixed  $data         JSON from Flexibee
     * @param string $resourceName Resource name in Flexibee
     *
     * @return mixed
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    protected function setCodeId($data, $resourceName)
    {
        if (!isset($data->{$resourceName})) {
            throw new MapperException("Unknown response, 'code:' prefix missing?!");
        }

        foreach ($data->{$resourceName} as $iterator => $row) {
            if (isset($row->{"external-ids"}[0])
                && substr($row->{"external-ids"}[0],0,5) === "code:"
            ) {
                $data->{$resourceName}[$iterator]->id =
                    $row->{"external-ids"}[0];
            }
        }
        return $data;
    }

    /**
     * Find single record
     *
     * @param \UniMapper\Query\FindOne $query Query
     *
     * @return \UniMapper\Entity|false
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    public function findOne(\UniMapper\Query\FindOne $query)
    {
        $resource = $this->getResource($query->entityReflection);

        // Create URL
        $url = rawurlencode($resource)
            . "/" . rawurlencode($query->primaryValue)
            . ".json";

        // Add custom fields from entity property definitions
        $parameters = array();

        // Add custom fields from entity properties definitions
        $parameters[] = "detail=custom:" . rawurlencode(implode(",", $this->getSelection($query->entityReflection)));

        // Try to get IDs as 'code:...'
        $parameters[] = "code-as-id=true";

        // Get data
        $data = $this->connection->get($url . "?" . implode("&", $parameters));

        // Check if request failed
        if (isset($data->success)
            && $data->success === "false") {
            throw new MapperException($data->message);
        }

        if (!isset($data->{$resource}[0])) {
            return false;
        }

        $entityClass = $query->entityReflection->getClassName();

        return $this->mapEntity(
            $entityClass,
            $this->setCodeId($data, $resource)->{$resource}[0]
        );
    }

    /**
     * FindAll
     *
     * @param \UniMapper\Query\FindAll $query Query
     *
     * @return \UniMapper\EntityCollection|false
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    public function findAll(\UniMapper\Query\FindAll $query)
    {
        $resource = $this->getResource($query->entityReflection);

        // Get URL
        $url = rawurlencode($resource);

        // Apply conditions
        if (count($query->conditions > 0)) {
            $url .= "/" . rawurlencode("(" . $this->convertConditions($this->translateConditions($query->entityReflection, $query->conditions)) . ")");
        }

        // Set response type
        $url .= ".json";

        // Define additional parameters
        $parameters = array();

        // Add order
        if (count($query->orderBy) > 0) {
            $parameters = $this->convertOrder($query->orderBy, $query);
        }

        $parameters[] = "start=" . (int) $query->offset;
        $parameters[] = "limit=" . (int) $query->limit;

        // Add custom fields from entity properties definitions
        $parameters[] = "detail=custom:" . rawurlencode(implode(",", $this->getSelection($query->entityReflection)));

        // Try to get IDs as 'code:...'
        $parameters[] = "code-as-id=true";

        // Request data
        $data = $this->connection->get($url . "?" . implode("&", $parameters));

        // Check if request failed
        if (isset($data->success)
            && $data->success === "false") {
            throw new MapperException($data->message);
        }

        if (count($data->{$resource}) === 0) {
            return false;
        }

        // Set ID and return data
        return $this->mapCollection(
            $query->entityReflection->getClassName(),
            $this->setCodeId($data, $resource)->{$resource}
        );
    }

    /**
     * Custom query
     *
     * @param \UniMapper\Query\Custom $query Query
     *
     * @return mixed
     */
    public function custom(\UniMapper\Query\Custom $query)
    {
        $url = rawurlencode($this->getResource($query->entityReflection));
        if ($query->query) {
            $url .= $query->query;
        } else {
            $url .= ".json";
        }

        if ($query->contentType) {
            $contentType = $query->contentType;
        } else {
            $contentType = "application/json";
        }

        if ($query->method === \UniMapper\Query\Custom::METHOD_GET) {
            return $this->connection->get($url);
        } elseif ($query->method === \UniMapper\Query\Custom::METHOD_PUT || $query->method === \UniMapper\Query\Custom::METHOD_POST) {
            return $this->connection->put($url, $query->data, $contentType);
        } elseif ($query->method === \UniMapper\Query\Custom::METHOD_DELETE) {
            return $this->connection->delete($url);
        }

        throw new MapperException("Not implemented!");
    }

    public function count(\UniMapper\Query\Count $query)
    {
        // Get URL
        $url = rawurlencode($this->getResource($query->entityReflection));

        // Apply conditions
        if (count($query->conditions > 0)) {
            $url .= "/" . rawurlencode("(" . $this->convertConditions($this->translateConditions($query->entityReflection, $query->conditions)) . ")");
        }

        $result = $this->connection->get($url . ".json?detail=id&add-row-count=true");
        return $result->{"@rowCount"};
    }

    /**
     * Insert
     *
     * @param \UniMapper\Query\Insert $query Query
     *
     * @return mixed|null
     */
    public function insert(\UniMapper\Query\Insert $query)
    {
        $resource = $this->getResource($query->entityReflection);

        $data = $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            array(
                "@update" => "ok",
                $resource => $this->unmapEntity($query->entity)
            )
        );

        if ($query->returnPrimaryValue) {
            if (isset($data->results)) {
                foreach ($data->results as $result) {
                    if (isset($result->ref)
                        && strpos($result->ref, $resource) !== false)
                    {
                        if (isset($result->code)) {
                            return "code:" . $result->code;
                        } elseif (isset($result->id)) {
                            return $result->id;
                        }
                    }
                }
            }
            throw new MapperException("Can not retrieve inserted primary value!");
        }
    }

    protected function convertConditions(array $conditions)
    {
        $result = null;

        foreach ($conditions as $condition) {

            if (is_array($condition[0])) {
                // Nested conditions

                list($nestedConditions, $joiner) = $condition;
                $converted = "(" . $this->convertConditions($nestedConditions) . ")";
                // Add joiner if not first condition
                if ($result !== null) {
                    $result .= " " . $joiner . " ";
                }
                $result .= $converted;

            } else {
                // Simple condition

                list($propertyName, $operator, $value, $joiner) = $condition;

                // Value
                if (is_array($value)) {
                    $value = "('" . implode("','", $value) . "')";
                } elseif ($value instanceof \DateTime) {
                    $value = "'" . $value->format(self::DATETIME_FORMAT) . "'";
                } else {
                    $leftPercent = $rightPercent = false;
                    if (substr($value, 0, 1) === "%") {
                        $value = substr($value, 1);
                        $leftPercent = true;
                    }
                    if (substr($value, -1) === "%") {
                        $value = substr($value, 0, -1);
                        $rightPercent = true;
                    }
                    $value = "'" . $value . "'";
                }

                // Operator
                if ($operator === "COMPARE") {
                    if ($rightPercent && !$leftPercent) {
                        $operator = "BEGINS";
                    } elseif ($leftPercent && !$rightPercent) {
                        $operator = "ENDS";
                    } else {
                        $operator = "LIKE SIMILAR";
                    }
                }

                $formatedCondition = $propertyName . " " . $operator . " " . $value;

                // Check if is it first condition
                if ($result === null) {
                    $result = $formatedCondition;
                } else {
                    $result .= " " . $joiner . " " . $formatedCondition;
                }
            }
        }

        return $result;
    }

    /**
     * Convert order to URL format
     *
     * @param array            $orderBy
     * @param \UniMapper\Query $query
     *
     * @return array
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    protected function convertOrder(array $orderBy, \UniMapper\Query $query)
    {
        $result = array();
        foreach ($orderBy as $item) {

            // Set direction
            $direction = "D";
            if ($item[1] === "asc") {
                $direction = "A";
            }

            // Map property name to defined mapping definition
            $properties = $query->entityReflection->getProperties();
            $propertyName = $properties[$item[0]]->getMappedName();

            $result[] = "order=" . rawurlencode($propertyName  . "@" . $direction);
        }
        return $result;
    }

    /**
     * Update
     *
     * @param \UniMapper\Query\Update $query Query
     *
     * @return boolean
     */
    public function update(\UniMapper\Query\Update $query)
    {
        $resource = $this->getResource($query->entityReflection);

        $values = $this->unmapEntity($query->entity);
        if (empty($values)) {
            return false;
        }

        $xml = new \SimpleXMLElement('<winstrom version="1.0" />');
        $xmlResource = $xml->addChild($resource);
        $xmlResource->addAttribute(
            "filter",
            $this->convertConditions(
                $this->translateConditions($query->entityReflection, $query->conditions)
            )
        );


        foreach ($values as $name => $value) {
            if (!is_array($value)) {    // @todo skip arrays temporary

                // @todo Experimental support for @attribute (@removeAll)
                $specialAttributes = array();
                if (strpos($name, "@") !== false) {
                    $specialAttributes = explode("@", $name);
                    $name = array_shift($specialAttributes);
                }

                $itemResource = $xmlResource->addChild($name, $value);
                foreach ($specialAttributes as $attribute) {
                    $itemResource->addAttribute($attribute, $value);
                }
            }
        }

        $result = $this->connection->put(
            rawurlencode($resource) . ".xml",
            $xml->asXML(),
            "application/xml"
        );

        return true;
    }

    protected function getSelection(Reflection\Entity $entityReflection, array $selection = array())
    {
        // Escape properties with @ char (polozky@removeAll), @showAs ...
        $selection = parent::getSelection($entityReflection, $selection);
        foreach ($selection as $index => $item) {

            if ($this->endsWith($item, "@removeAll")) {
                $selection[$index] = substr($item, 0, -10);
            } elseif ($this->endsWith($item, "@showAs") || $this->endsWith($item, "@action")) {
                $selection[$index] = substr($item, 0, -7);
            }
        }
        return $selection;
    }

    private function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

}