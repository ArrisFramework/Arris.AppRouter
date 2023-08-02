<?php

namespace Arris\AppRouter\Exceptions;

use Throwable;

class AppRouterCommonException extends \RuntimeException
{
    protected array $_info;

    /**
     * Exception constructor
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array $info
     */
    public function __construct(string $message = "", int $code = 0 , Throwable $previous = null, array $info = [])
    {
        $this->_info = $info;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get custom field
     *
     * @param $key
     * @return array|mixed|null
     */
    public function getInfo($key = null)
    {
        return is_null($key) ? $this->_info : (array_key_exists($key, $this->_info) ? $this->_info[$key] : null);
    }

}