<?php

namespace UniMapper\Flexibee;

use UniMapper\Association;

class Query implements \UniMapper\Adapter\IQuery
{

    const GET = "get",
          PUT = "put",
          DELETE = "delete";

    public $id;
    public $data;
    public $evidence;
    public $filter;
    public $method;
    public $parameters = [];
    public $resultCallback;
    public $associations = [];
    public $includes = [];
    public $relations = [];

    public function __construct($evidence, $method = self::GET, $data = [])
    {
        $this->evidence = $evidence;
        $this->method = $method;
        $this->data = $data;
    }

    public function setConditions(array $conditions)
    {
        $this->filter = $this->_formatConditions($conditions);
    }

    public function setAssociations(array $associations)
    {
        foreach ($associations as $association) {

            if ($association instanceof Association\ManyToMany) {
                // M:N

                if ($association->getJoinKey() === "vazby") {

                    $this->relations[] = "vazby";
                    $this->includes[] = "/winstrom/" . $this->evidence . "/vazby/vazba/" . $association->getReferenceKey();
                } elseif ($association->getJoinKey() === "uzivatelske-vazby") {

                    $this->relations[] = "uzivatelske-vazby";
                    $this->includes[] = "/winstrom/" . $this->evidence . "/uzivatelske-vazby/uzivatelska-vazba/object";
                } else {
                    throw new \Exception("Unexpected association key on M:N!");
                }
            } elseif ($association instanceof Association\ManyToOne) {
                // N:1

                $this->includes[] = "/" . $this->evidence . "/" . $association->getReferenceKey();
                $this->relations[] = $association->getReferenceKey(); // Due to attachments
            } elseif ($association instanceof Association\OneToOne) {
                // 1:1

                $this->includes[] = "/" . $this->evidence . "/" . $association->getForeignKey();
                $this->relations[] = $association->getForeignKey(); // Due to attachments
            } else {
                throw new \Exception("Unsupported association " . get_class($association) . "!");
            }
        }
    }

    public function getRaw()
    {
        $url = $this->evidence;

        if ($this->method === self::PUT) {
            $this->parameters["code-in-response"] = "true";
        } elseif ($this->method === self::GET) {

            $this->parameters["code-as-id"] = "true"; // @todo should be optional

            if ($this->filter) {
                $url .= "/(" . rawurlencode($this->filter) . ")";
            }
        }

        if ($this->id && $this->method !== self::PUT) {
            $url .= "/" . rawurlencode($this->id);
        }

        $url .= ".json";

        // Join relations & includes
        if ($this->includes) {
            $this->parameters["detail"] = "full";
            $this->parameters["includes"] = implode(",", $this->includes);
        }
        if ($this->relations) {
            $this->parameters["detail"] = "full";
            $this->parameters["relations"] = implode(",", $this->relations);
        }

        if ($this->parameters) {
            $url .= "?" . http_build_query($this->parameters);
        }

        return $url;
    }

    private function _formatConditions(array $conditions)
    {
        $result = "";

        foreach ($conditions as $condition) {

            if (is_array($condition[0])) {
                // Nested conditions

                list($nestedConditions, $joiner) = $condition;
                $converted = "(" . $this->_formatConditions($nestedConditions) . ")";
                // Add joiner if not first condition
                if ($result !== "") {
                    $result .= " " . $joiner . " ";
                }
                $result .= $converted;

            } else {
                // Simple condition

                list($name, $operator, $value, $joiner) = $condition;

                // Value
                if (is_array($value)) {
                    $value = "('" . implode("','", $value) . "')";
                } elseif ($value instanceof \DateTime) {
                    $value = "'" . $value->format(Mapping::DATETIME_FORMAT) . "'";
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

                $formatedCondition = $name . " " . $operator . " " . $value;

                // Check if is it first condition
                if ($result !== "") {
                    $result .= " " . $joiner . " ";
                }

                $result .=  $formatedCondition;
            }
        }

        return $result;
    }

}