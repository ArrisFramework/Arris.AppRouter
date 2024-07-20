<?php

namespace Arris\Exceptions;

use Throwable;

class AppRouterCommonException extends \RuntimeException
{
    protected array $_info;

    /**
     * Exception constructor
     *
     * @param string $message
     * @param int $code
     * @param array $info
     */
    public function __construct(string $message = "", int $code = 0 , array $info = [])
    {
        $this->_info = $info;

        parent::__construct($message, $code, null);
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

    public function getError()
    {
        return 'Exception thrown from [' . $this->getFile() . '] with message: [' . $this->getMessage() . '] at line # ' . $this->getLine();
    }

}