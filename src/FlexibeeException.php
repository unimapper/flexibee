<?php

namespace UniMapper\Exceptions;

class FlexibeeException extends \Exception
{

    /** @var \Httpful\Request $request Request */
    protected $request;

    /**
     * Constructor
     *
     * @param string           $message Message
     * @param \Httpful\Request $request Request
     * @param string           $code    Code
     */
    public function __construct($message, \Httpful\Request $request = null)
    {
        parent::__construct($message);
        $this->request = $request;
    }

    /**
     * Get request
     *
     * @return \Httpful\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

}