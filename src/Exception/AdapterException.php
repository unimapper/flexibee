<?php

namespace UniMapper\Flexibee\Exception;

class AdapterException extends \UniMapper\Exception\AdapterException
{

    const TYPE_UPDATE_FORBIDDEN = "Atribut update='fail' zakazuje změnu existujícího záznamu.";
    const TYPE_SELECTONE_RECORDNOTFOUND = "Record not found in data source.";

    private $type;

    public function __construct($message, $query, $response = null)
    {
        if (isset($response->results[0]->errors[0])) {
            $message = json_encode($response->results[0]->errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (substr($response->results[0]->errors[0]->message, 0, strlen(self::TYPE_UPDATE_FORBIDDEN)) === self::TYPE_UPDATE_FORBIDDEN) {
                $this->type = self::TYPE_UPDATE_FORBIDDEN;
            }
        } elseif (isset($response->message)) {

            if ($response->message === self::TYPE_SELECTONE_RECORDNOTFOUND) {
                $this->type = self::TYPE_SELECTONE_RECORDNOTFOUND;
            }
            $message = $response->message;
        }

        parent::__construct($message, $query);
    }

    public function getType()
    {
        return $this->type;
    }

}