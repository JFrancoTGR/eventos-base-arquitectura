<?php

class RegistrationException extends RuntimeException
{
    private $errorCode;
    private $httpStatus;

    public function __construct($errorCode, $message, $httpStatus = 400)
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }
}