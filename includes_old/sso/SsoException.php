<?php
/**
 * SSO module exception — used for controlled error handling and user-facing messages.
 */
class SsoException extends Exception
{
    /** @var string Stable machine-readable code for logging / flash keys */
    private string $errorCode;

    public function __construct(string $message, string $errorCode = 'sso_error', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}