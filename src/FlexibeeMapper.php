<?php

namespace UniMapper\Mapper;

use UniMapper\Query\Object\Order,
    UniMapper\Reflection\EntityReflection,
    UniMapper\Connection\FlexibeeConnection,
    UniMapper\Exceptions\MapperException;

/**
 * Flexibee mapper can be generally used to communicate between repository and
 * Flexibee REST API.
 */
class FlexibeeMapper extends \UniMapper\Mapper
{

    /** @var \DibiConnection $connection Dibi connection */
    protected $connection;

    public function __construct($name, FlexibeeConnection $connection)
    {
        parent::__construct($name);
        $this->connection = $connection;
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

        if (count($query->conditions) > 1) {
            throw new MapperException("Only one condition is allowed!");
        }

        $data = $this->connection->sendPut(
            $this->connection->getUrl() . "/" . rawurlencode($resource) . ".json",
            json_encode(
                array(
                    "winstrom" => array(
                        $resource => array(
                            "@action" => "delete",
                            "@id" => $query->conditions[0]->value
                        )
                    )
                )
            )
        );

        return $this->getStatus($data);
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
        if (!isset($data->winstrom->{$resourceName})) {
            throw new MapperException("Unknown response, 'code:' prefix missing?!");
        }

        foreach ($data->winstrom->{$resourceName} as $iterator => $row) {
            if (isset($row->{"external-ids"}[0])
                && substr($row->{"external-ids"}[0],0,5) === "code:"
            ) {
                $data->winstrom->{$resourceName}[$iterator]->id =
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
        $url = $this->connection->getUrl()
            . "/" . rawurlencode($resource)
            . "/" . rawurlencode($query->primaryValue)
            . ".json";

        // Add custom fields from entity property definitions
        $parameters = array();

        // Add custom fields from entity properties definitions
        $parameters[] = "detail=custom:" . rawurlencode(implode(",", $this->getSelection($query->entityReflection)));

        // Try to get IDs as 'code:...'
        $parameters[] = "code-as-id=true";

        // Get data
        $data = $this->connection->sendGet($url . "?" . implode("&", $parameters));

        // Check if request failed
        if (isset($data->winstrom->success)
            && $data->winstrom->success === "false") {
            throw new MapperException($data->winstrom->message);
        }

        if (!isset($data->winstrom->{$resource}[0])) {
            return false;
        }

        $entityClass = $query->entityReflection->getName();

        return $this->dataToEntity(
            $this->setCodeId($data, $resource)->winstrom->{$resource}[0],
            new $entityClass
        );
    }

    /**
     * FindAll
     *
     * @param \UniMapper\Query\FindAll $query Query
     *
     * @return integer|mixed
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    public function findAll(\UniMapper\Query\FindAll $query)
    {
        $resource = $this->getResource($query->entityReflection);

        // Get URL
        $url = $this->connection->getUrl() . "/" . rawurlencode($resource);

        // Apply conditions
        if (count($query->conditions > 0)) {
            $url .= "/" . $this->getConditions($query);
        }

        // Set response type
        $url .= ".json";

        // Define additional parameters
        $parameters = array();

        // Add order
        if (count($query->orders) > 0) {
            $parameters = $this->convertOrder($query->orders, $query);
        }

        if ($query->offset) {
            $parameters[] = "start=" . $query->offset;
        }
        if ($query->limit) {
            $parameters[] = "limit=" . $query->limit;
        }

        // Add custom fields from entity properties definitions
        $parameters[] = "detail=custom:" . rawurlencode(implode(",", $this->getSelection($query->entityReflection)));

        // Try to get IDs as 'code:...'
        $parameters[] = "code-as-id=true";

        // Request data
        $data = $this->connection->sendGet($url . "?" . implode("&", $parameters));

        // Check if request failed
        if (isset($data->winstrom->success)
            && $data->winstrom->success === "false") {
            throw new MapperException($data->winstrom->message);
        }

        if (count($data->winstrom->{$resource}) === 0) {
            return false;
        }

        // Set ID and return data
        return $this->dataToCollection(
            $this->setCodeId($data, $resource)->winstrom->{$resource},
            $query->entityReflection->getName(),
            $query->entityReflection->getPrimaryProperty()
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
        $url = $this->connection->getUrl() . "/" . rawurlencode($this->getResource($query->entityReflection)) . "/" . $query->query;
        if ($query->method === \UniMapper\Query\Custom::METHOD_GET) {
            return $this->connection->sendGet($url)->winstrom;
        } elseif ($query->method === \UniMapper\Query\Custom::METHOD_PUT || $query->method === \UniMapper\Query\Custom::METHOD_POST) {
            return $this->connection->sendPut($url, $query->data);
        }

        throw new MapperException("Not implemented!");
    }

    public function count(\UniMapper\Query\Count $query)
    {
        // Get URL
        $url = $this->connection->getUrl() . "/" . rawurlencode($this->getResource($query->entityReflection));

        // Apply conditions
        if (count($query->conditions > 0)) {
            $url .= "/" . $this->getConditions($query);
        }

        $result = $this->connection->sendGet($url . ".json?detail=id&add-row-count=true");
        return $result->winstrom->{"@rowCount"};
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

        $data = $this->connection->sendPut(
            $this->connection->getUrl() . "/" . rawurlencode($resource) . ".json?code-in-response=true",
            json_encode(
                array(
                    "winstrom" => array(
                        "@update" => "ok",
                        $resource => $this->entityToData($query->entity)
                    )
                )
            )
        );

        $this->getStatus($data);

        if ($query->returnPrimaryValue) {
            if (isset($data->winstrom->results)) {
                foreach ($data->winstrom->results as $result) {
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

     /**
     * Get status of result of send request to Flexibee
     *
     * @param string $data Data from sendPut
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getStatus($data)
    {
        // Check if request failed
        if (isset($data->winstrom->success)
            && $data->winstrom->success === "false") {

            if (isset($data->winstrom->results[0]->errors[0])) {

                $errorDetails = $data->winstrom->results[0]->errors[0];
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
                throw new \Exception("Flexibee error: {$error}");
            }

            if (isset($data->winstrom->message)) {
                throw new \Exception("Flexibee error: {$data->winstrom->message}");
            }

            throw new MapperException("An unknown flexibee error occurred");
        }

        return $data;
    }

    /**
     * Get mapped conditions from query
     *
     * @param \UniMapper\Query $query Query object
     *
     * @return string
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    protected function getConditions(\UniMapper\Query $query)
    {
        $properties = $query->entityReflection->getProperties($this->name);

        $result = null;
        foreach ($query->conditions as $condition) {

            $propertyName = $condition->getExpression();

            // Apply defined mapping from entity
            $mappedPropertyName = $properties[$propertyName]->getMapping()->getName($this->name);
            if ($mappedPropertyName) {
                $propertyName = $mappedPropertyName;
            }

            $operator = $condition->getOperator();
            if ($operator === "COMPARE") {
                $operator = "LIKE SIMILAR";
            }

            $value = $condition->getValue();
            if (is_array($value)) {
                $value = "(" . implode(",", $value) . ")";
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
            }

            $operator = $condition->getOperator();
            if ($operator === "COMPARE") {
                if ($rightPercent && !$leftPercent) {
                    $operator = "BEGINS";
                } elseif ($leftPercent && !$rightPercent) {
                    $operator = "ENDS";
                } else {
                    $operator = "LIKE SIMILAR";
                }
            }

            $url = $propertyName . $operator . "'" . $value . "'";

            // Check if is it first condition
            if ($result == null) {
                $result = $url;
            } else {
                $result .= " and " . $url;
            }
        }

        return rawurlencode("(" . $result . ")");
    }

    /**
     * Convert order to URL format
     *
     * @param array            $orderBy Collection of \UniMapper\Query\Object\Order
     * @param \UniMapper\Query $query   Query object
     *
     * @return array
     *
     * @throws \UniMapper\Exceptions\MapperException
     */
    protected function convertOrder(array $orderBy, \UniMapper\Query $query)
    {
        $result = array();
        foreach ($orderBy as $order) {

            if (!$order instanceof Order) {
                throw new MapperException("Order collection must contain only \UniMapper\Query\Object\Order objects!");
            }

            // Set direction
            $direction = "D";
            if ($order->asc) {
                $direction = "A";
            }

            // Map property name to defined mapping definition
            $properties = $query->entityReflection->getProperties($this->name);

            // Skip properties not related to this mapper
            if (!isset($properties[$order->propertyName])) {
                continue;
            }

            // Map property
            $mapping = $properties[$order->propertyName]->getMapping();
            if ($mapping) {
                $propertyName = $mapping->getName($this->name);
            } else {
                $propertyName = $order->propertyName;
            }

            $result[] = "order=" . rawurlencode($propertyName  . "@" . $direction);
        }
        return $result;
    }

    /**
     * Update
     *
     * @param \UniMapper\Query\Update $query Query
     *
     * @return mixed
     *
     * @todo After $primaryProperty implementation get primary key automatically
     * @todo rename to updateOne() ??
     */
    public function update(\UniMapper\Query\Update $query)
    {
        $resource = $this->getResource($query->entityReflection);

        if (count($query->conditions) > 1) {
            throw new MapperException("More then 1 condition is not allowed");
        }

        $properties = $this->entityToData($query->entity);
        $special["@id"] = $query->conditions[0]->getValue();

        // @todo workaround: bug in Flexibee? @id must be first!
        $properties = array_merge($special, $properties);

        $data = $this->connection->sendPut(
            $this->connection->getUrl() . "/" . rawurlencode($resource) . ".json",
            json_encode(
                array(
                    "winstrom" => array(
                        $resource => $properties
                    )
                )
            )
        );

        return $this->getStatus($data);
    }

    protected function getSelection(EntityReflection $entityReflection, array $selection = array())
    {
        $selection = parent::getSelection($entityReflection, $selection);

        // Remove properties with @ char (polozky@removeAll)
        foreach ($selection as $index => $item) {
            if (strpos($item, "@") !== false) {
                unset($selection[$index]);
            }
        }
        return $selection;
    }

}