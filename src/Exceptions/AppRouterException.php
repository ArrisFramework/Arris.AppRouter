<?php

namespace Arris\Exceptions;

use Throwable;

/**
 * Корневой класс исключений AppRouter
 * От него наследуются остальные методы исключений класса роутинга
 */
class AppRouterException extends AppRouterCommonException
{
    protected array $_info;

    /**
     * Exception constructor
     *
     * @param string $message
     * @param int $code
     * @param array $info
     */
    public function __construct(string $message = "", int $code = 0,  array $info = [])
    {
        $this->_info = $info;

        parent::__construct($message, $code, $info);
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