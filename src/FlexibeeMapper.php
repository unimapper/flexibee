<?php

namespace UniMapper\Mapper;

use UniMapper\Reflection\Entity\Property\Association\HasMany,
    UniMapper\Reflection\Entity\Property\Association\BelongsToMany,
    UniMapper\Query,
    UniMapper\Connection\FlexibeeConnection,
    UniMapper\Exceptions\MapperException;

/**
 * Flexibee mapper can be generally used to communicate between repository and
 * Flexibee REST API.
 */
class FlexibeeMapper extends \UniMapper\Mapper
{

    const DATETIME_FORMAT = "Y-m-d\TH:i:sP";

    /** @var \DibiConnection $connection */
    private $connection;

    public function __construct($name, FlexibeeConnection $connection)
    {
        parent::__construct($name);
        $this->connection = $connection;
    }

    protected function unmapValue($value, $entity = null, $property = null )
    {
        $value = parent::unmapValue($value, $entity, $property );
        if ($value === null) {
            $value = "";
        } elseif ($value instanceof \DateTime) {
            $value = $value->format(self::DATETIME_FORMAT);
        }
        return $value;
    }

    /**
     * Delete record by some conditions
     *
     * @param string $resource
     * @param array  $conditions
     */
    public function delete($resource, array $conditions)
    {
        $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            [$resource => ["@filter" => $this->convertConditions($conditions), "@action" => "delete"]]
        );
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
    private function setCodeId($data, $resourceName)
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
     * Find single record identified by primary value
     *
     * @param string $resource
     * @param mixed  $primaryName
     * @param mixed  $primaryValue
     * @param array  $associations
     *
     * @return mixed
     */
    public function findOne($resource, $primaryName, $primaryValue, array $associations = [])
    {
        $url = rawurlencode($resource) . "/" . rawurlencode($primaryValue) . ".json";

        $parameters = [];
        $parameters[] = "code-as-id=true";

        // Associations
        $associated = [];
        $includes = [];
        foreach ($associations as $propertyName => $association) {

            if ($association instanceof HasMany) {
                // M:N

                $relations = $this->connection->get(
                    rawurlencode($resource) . "/" . rawurlencode($primaryValue) . "/vazby.json?code-as-id=true&detail=full&includes=/winstrom/vazba/a,/winstrom/vazba/b"
                );

                if (isset($relations->vazba)) {

                    foreach ($relations->vazba as $index => $relation) {

                        if ($relation->typVazbyK === $association->getJoinResource()) {
                            foreach ($relation->{$association->getReferenceKey()} as $index => $item) {
                                $associated[$propertyName] = new \stdClass;
                                $associated[$propertyName]->{$index} = $item;
                            }
                        }
                    }
                }
            } elseif ($association instanceof BelongsToMany) {
                // 1:N

                $includes[$association->getForeignKey()] = $propertyName;
            } else {
                throw new MapperException("Unsupported association " . get_class($association) . "!");
            }
        }

        // Add includes
        if ($includes) {
            $includeItems = implode(",", array_keys($includes));
            $parameters[] = "includes=" . str_replace(",", ",/" . $resource . "/", $includeItems) . "&detail=full";
            $parameters[] = "relations=" . $includeItems; // Because of attachments
        }

        // Query on server
        $result = $this->connection->get($url . "?" . implode("&", $parameters));
        if (!isset($result->{$resource}[0])) {
            return false;
        }

        // Join associated results
        foreach ($associated as $propertyName => $values) {
            $result->{$resource}[0]->{$propertyName} = $values;
        }

        // Join includes results
        foreach ($includes as $includeKey => $propertyName) {
            $result->{$resource}[0]->{$propertyName} = $result->{$resource}[0]->{$includeKey};
            unset($result->{$resource}[0]->{$includeKey});
        }

        return $this->setCodeId($result, $resource)->{$resource}[0];
    }

    /**
     * Find records
     *
     * @param string  $resource
     * @param array   $selection
     * @param array   $conditions
     * @param array   $orderBy
     * @param integer $limit
     * @param integer $offset
     * @param array   $associations
     *
     * @return array|false
     */
    public function findAll($resource, array $selection = [], array $conditions = [], array $orderBy = [], $limit = 0, $offset = 0, array $associations = [])
    {
        // Get URL
        $url = rawurlencode($resource);

        // Apply conditions
        if (count($conditions) > 0) {
            $url .= "/" . rawurlencode("(" . $this->convertConditions($conditions) . ")");
        }

        // Set response type
        $url .= ".json";

        // Define additional parameters
        $parameters = $this->convertOrderBy($orderBy);

        // Offset and limit must be defined even if null given
        $parameters[] = "start=" . (int) $offset;
        $parameters[] = "limit=" . (int) $limit;

        // Try to get IDs as 'code:...'
        $parameters[] = "code-as-id=true";

        // Associations
        $includes = [];
        $relations = [];
        foreach ($associations as $propertyName => $association) {

            if ($association instanceof HasMany) {
                // M:N

                if (!in_array("vazby", $relations)) {
                    $relations[] = 'vazby';
                }
            } elseif ($association instanceof BelongsToMany) {
                // 1:N

                $includes[$association->getForeignKey()] = $propertyName;
            } else {
                throw new MapperException("Unsupported association " . get_class($association) . "!");
            }
        }

        // Add includes
        if ($includes) {

            $includeItems = [];
            foreach (array_keys($includes) as $index => $includeItem) {
                $includeItems[$index] = "/" . $resource . "/" . $includeItem;
            }
            $parameters[] = "includes=" . implode(",", $includeItems);
            $relations = array_merge($relations, array_keys($includes));
        }

        if ($relations) {
            $parameters[] = "relations=" . implode(",", $relations);
        }

        // Add custom fields from entity properties definitions
        if ($selection) {
            $parameters[] = "detail=custom:" . rawurlencode(implode(",", $this->escapeProperties($selection)));
        }

        // Query on server
        $result = $this->connection->get($url . "?" . implode("&", $parameters));
        if (count($result->{$resource}) === 0) {
            return false;
        }

        // Join includes results
        if ($includes) {

            foreach ($result->{$resource} as $index => $item) {

                foreach ($includes as $includeKey => $propertyName) {
                    $result->{$resource}[$index]->{$propertyName} = $item->{$includeKey};
                    unset($result->{$resource}[$index]->{$includeKey});
                }
            }
        }

        // Set ID and return data
        return $this->setCodeId($result, $resource)->{$resource};
    }

    /**
     * Custom query
     *
     * @param string $resource
     * @param string $query
     * @param string $method
     * @param string $contentType
     * @param mixed  $data
     *
     * @return mixed
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    public function custom($resource, $query, $method, $contentType, $data)
    {
        $url = rawurlencode($resource);
        if (empty($query)) {
            $query .= ".json";
        }
        $url .= $query;

        if (empty($contentType)) {
            $contentType = "application/json";
        }

        if ($method === Query\Custom::METHOD_GET) {
            return $this->connection->get($url);
        } elseif ($method === Query\Custom::METHOD_PUT || $method === Query\Custom::METHOD_POST) {
            return $this->connection->put($url, $data, $contentType);
        } elseif ($method === Query\Custom::METHOD_DELETE) {
            return $this->connection->delete($url);
        }

        throw new MapperException("Undefined custom method '" . $method . "' used!");
    }

    public function count($resource, array $conditions)
    {
        // Get URL
        $url = rawurlencode($resource);

        // Apply conditions
        if (count($conditions > 0)) {
            $url .= "/" . rawurlencode("(" . $this->convertConditions($conditions) . ")");
        }

        return $this->connection->get($url . ".json?detail=id&add-row-count=true")->{"@rowCount"};
    }

    /**
     * Insert
     *
     * @param string $resource
     * @param array  $values
     *
     * @return mixed Primary value
     */
    public function insert($resource, array $values)
    {
        $result = $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            array(
                "@update" => "fail",
                $resource => $values
            )
        );

        if (isset($result->results)) {
            foreach ($result->results as $result) {
                if (isset($result->ref)
                    && strpos($result->ref, $resource) !== false
                ) {
                    if (isset($result->code)) {
                        return "code:" . $result->code;
                    } elseif (isset($result->id)) {
                        return $result->id;
                    }
                }
            }
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

                // Compare
                if ($operator === "COMPARE") {
                    if ($rightPercent && !$leftPercent) {
                        $operator = "BEGINS";
                    } elseif ($leftPercent && !$rightPercent) {
                        $operator = "ENDS";
                    } else {
                        $operator = "LIKE SIMILAR";
                    }
                }

                // IS, IS NOT
                if (($operator === "IS NOT" || $operator === "IS") && $value === "''") {
                    $value = "empty";
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

    private function convertOrderBy($items)
    {
        $result = [];
        foreach ($items as $name => $direction) {

            if ($direction === "asc") {
                $direction = "A";
            } else {
                $direction = "D";
            }
            $result[] = "order=" . rawurlencode($name  . "@" . $direction);
        }
        return $result;
    }

    /**
     * Update data by set of conditions
     *
     * @param string $resource
     * @param array  $values
     * @param array  $conditions
     */
    public function update($resource, array $values, array $conditions)
    {
        $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            [
                "@create" => "fail",
                $resource => ["@filter" => $this->convertConditions($conditions)] + $values
            ]
        );
    }

    /**
     * Update single record
     *
     * @param string $resource
     * @param string $primaryName
     * @param mixed  $primaryValue
     * @param array  $values
     */
    public function updateOne($resource, $primaryName, $primaryValue, array $values)
    {
        $values[$primaryName] = $primaryValue;
        $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            ["@create" => "fail", $resource => $values]
        );
    }

    /**
     * Escape properties with @ char (polozky@removeAll), @showAs ...
     *
     * @param array $properties
     *
     * @return array
     */
    private function escapeProperties(array $properties)
    {
        foreach ($properties as $index => $item) {

            if ($this->endsWith($item, "@removeAll")) {
                $properties[$index] = substr($item, 0, -10);
            } elseif ($this->endsWith($item, "@showAs") || $this->endsWith($item, "@action")) {
                $properties[$index] = substr($item, 0, -7);
            }
        }
        return $properties;
    }

    private function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

}
