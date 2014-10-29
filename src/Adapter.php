<?php

namespace UniMapper\Flexibee;

use UniMapper\Association,
    UniMapper\Adapter\IQuery;

class Adapter extends \UniMapper\Adapter
{

    /** @var \UniMapper\Flexibee\Connection $connection */
    private $connection;

    public function __construct($name, Connection $connection)
    {
        parent::__construct($name, new Mapping);
        $this->connection = $connection;
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
        $query->resultCallback = function ($result) {
            return $result->stats->deleted === "0" ? false : true;
        };
        return $query;
    }

    public function createFindOne($evidence, $column, $primaryValue)
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

    public function createFind($evidence, array $selection = [], array $orderBy = [], $limit = 0, $offset = 0)
    {
        $query = new Query($evidence);

        $query->parameters["start"] = (int) $offset;
        $query->parameters["limit"] = (int) $limit;

        foreach ($orderBy as $name => $direction) {

            if ($direction === \UniMapper\Query\Find::ASC) {
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

                    $propertyName = $association¨->getPropertyName();

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
                    return "code:" . $result->code;
                } else {
                    return $result->id;
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

    public function execute(IQuery $query)
    {
        if ($query->method === Query::PUT) {
            $result = $this->connection->put($query->getRaw(), $query->data);
        } elseif ($query->method === Query::GET) {
            $result = $this->connection->get($query->getRaw());
        } elseif ($query->method === Query::DELETE) {
            $result = $this->connection->delete($query->getRaw());
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

}