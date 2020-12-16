<?php

namespace PhalApi\Xpay;

abstract class Base
{
    protected $config;
    protected $info;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 配置检查.
     *
     * @return bool
     */
    public function check()
    {
        return true;
    }

    /**
     * 验证通过后获取订单信息.
     *
     * @return type
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * 支付通知验证
     */
    abstract public function verifyNotify($notify);

    /**
     * 异步通知验证成功返回信息.
     */
    public function notifySuccess()
    {
        echo 'success';
    }

    /**
     * 异步通知验证失败返回信息.
     *
     * @return [type] [description]
     */
    public function notifyError()
    {
        echo 'fail';
    }
}
