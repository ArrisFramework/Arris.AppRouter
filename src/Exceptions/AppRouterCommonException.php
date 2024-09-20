<?php

namespace Arris\Exceptions;

use Throwable;

class AppRouterCommonException extends \RuntimeException
{
    protected array $_info;

    protected $uri = '';

    protected $httpMethod = 'ANY';

    protected $routeInfo = [];

    protected $routeRule = [];

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
        $this->uri = $info['uri'] ?: '';
        $this->httpMethod = $info['method'] ?: '';
        $this->routeInfo = $info['info'] ?: [];
        $this->routeRule = $info['rule'] ?: [];

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
        return is_null($key) ? $this->_info : (\array_key_exists($key, $this->_info) ? $this->_info[$key] : null);
    }

    public function getError(): string
    {
        $backtrace = $this->routeRule['backtrace'] ?: [ 'file' => $this->getFile(), 'line' => $this->getLine() ];
        return 'AppRouter throws exception: ' . $this->getMessage() . ', mentioned in ' . $backtrace['file'] . ' at line ' . $backtrace['line'];
    }

}