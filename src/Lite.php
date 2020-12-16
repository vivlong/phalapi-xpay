<?php

namespace PhalApi\Xpay;

class Lite
{
    /**
     * 支付驱动实例.
     *
     * @var object
     */
    private $payer;

    /**
     * 配置参数.
     *
     * @var type
     */
    private $config;

    /**
     * 获取引擎.
     *
     * @var string
     */
    private $engine;

    /**
     * 获取错误.
     *
     * @var string
     */
    public $error = '';

    public function __call($method, $arguments)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([&$this, $method], $arguments);
        } elseif (!empty($this->payer) && $this->payer && method_exists($this->payer, $method)) {
            return call_user_func_array([&$this->payer, $method], $arguments);
        }
    }

    /**
     * 设置配置信息.
     *
     * @param string $engine 要使用的支付引擎
     * @param array  $config 配置
     */
    public function set($engine)
    {
        $di = \PhalApi\DI();
        $this->engine = strtolower($engine);
        $this->config = [];
        $config = $di->config->get('app.Xpay.'.$this->engine);
        if (!$config) {
            $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['No engine config' => $this->engine]);

            return false;
        }
        $this->config = array_merge($this->config, $config);
        $engine = '\\PhalApi\\Xpay\\Engine\\'.ucfirst(strtolower($this->engine));
        $this->payer = new $engine($this->config);
        if (!$this->payer) {
            $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['No engine class' => $this->engine]);

            return false;
        }

        return true;
    }
}
