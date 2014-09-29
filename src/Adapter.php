<?php

namespace UniMapper\Flexibee;

use UniMapper\Reflection\Entity\Property\Association\ManyToMany,
    UniMapper\Reflection\Entity\Property\Association\OneToMany,
    UniMapper\Exception\AdapterException;

/**
 * Flexibee mapper can be generally used to communicate between repository and
 * Flexibee REST API.
 */
class Adapter extends \UniMapper\Adapter
{

    /** @var \UniMapper\Flexibee\Connection $connection */
    private $connection;

    public function __construct($name, Connection $connection)
    {
        parent::__construct($name, new Mapping);
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Delete record by some conditions
     *
     * @param string $resource
     * @param string $conditions
     */
    public function delete($resource, $conditions)
    {
        $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            [$resource => ["@filter" => $conditions, "@action" => "delete"]]
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
     * @throws \UniMapper\Exception\AdapterException
     */
    public function setCodeId($data, $resourceName)
    {
        if (!isset($data->{$resourceName})) {
            throw new AdapterException("Unknown response, 'code:' prefix missing?!");
        }

        foreach ($data->{$resourceName} as $index => $row) {

            if (isset($row->{"external-ids"}[0])
                && substr($row->{"external-ids"}[0], 0, 5) === "code:"
            ) {
                $data->{$resourceName}[$index]->id
                    = $row->{"external-ids"}[0];
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

            if ($association instanceof ManyToMany) {
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
            } elseif ($association instanceof OneToMany) {
                // 1:N

                $includes[$association->getForeignKey()] = $propertyName;
            } else {
                throw new AdapterException("Unsupported association " . get_class($association) . "!");
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
     * @param string  $selection
     * @param string  $conditions
     * @param array   $orderBy
     * @param integer $limit
     * @param integer $offset
     * @param array   $associations
     *
     * @throws \UniMapper\Exception\AdapterException
     *
     * @return array|false
     */
    public function find($resource, $selection = null, $conditions = null, $orderBy = null, $limit = 0, $offset = 0, array $associations = [])
    {
        $url = rawurlencode($resource);

        // Apply conditions
        if ($conditions) {
            $url .= "/" . rawurlencode("(" . $conditions . ")");
        }

        // Set response type
        $url .= ".json";

        // Define additional parameters
        $parameters = $orderBy;

        // Offset and limit must be defined even if null given
        $parameters[] = "start=" . (int) $offset;
        $parameters[] = "limit=" . (int) $limit;

        // Try to get IDs as 'code:...'
        $parameters[] = "code-as-id=true";

        // Associations
        $includes = [];
        $relations = [];
        foreach ($associations as $propertyName => $association) {

            if ($association instanceof ManyToMany) {
                // M:N

                if (!in_array("vazby", $relations)) {
                    $relations[] = 'vazby';
                }
            } elseif ($association instanceof OneToMany) {
                // 1:N

                $includes[$association->getForeignKey()] = $propertyName;
            } else {
                throw new AdapterException("Unsupported association " . get_class($association) . "!");
            }
        }

        // Add includes
        if ($includes) {

            $includeItems = [];
            foreach (array_keys($includes) as $index => $includeItem) {
                $includeItems[$index] = "/" . rawurlencode($resource) . "/" . $includeItem;
            }
            $parameters[] = "includes=" . implode(",", $includeItems);
            $relations = array_merge($relations, array_keys($includes));
        }

        if ($relations) {
            $parameters[] = "relations=" . implode(",", $relations);
        }

        // Add custom fields from entity properties definitions
        if ($selection) {
            $parameters[] = "detail=custom:" . rawurlencode($selection);
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

        return $this->setCodeId($result, $resource)->{$resource};
    }

    public function count($resource, $conditions)
    {
        // Get URL
        $url = rawurlencode($resource);

        // Apply conditions
        if ($conditions) {
            $url .= "/" . rawurlencode("(" . $conditions . ")");
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

    /**
     * Update data by set of conditions
     *
     * @param string $resource
     * @param array  $values
     * @param string $conditions
     */
    public function update($resource, array $values, $conditions = null)
    {
        $this->connection->put(
            rawurlencode($resource) . ".json?code-in-response=true",
            [
                "@create" => "fail",
                $resource => ["@filter" => $conditions] + $values
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

}