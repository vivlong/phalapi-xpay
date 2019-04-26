<?php
namespace PhalApi\Xpay;

abstract class Base {

    protected $config;

    public function __construct($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 配置检查
     * @return boolean
     */
    public function check() {
        return true;
    }

    /**
     * 支付通知验证
     */
    abstract public function verifyNotify($notify);

    /**
     * 异步通知验证成功返回信息
     */
    public function notifySuccess() {
        echo "success";
    }

    /**
     * 异步通知验证失败返回信息
     * @return [type] [description]
     */
    public function notifyError(){
        echo "fail";
    }

    final protected function formatPostkey($post, &$result, $key = '') {
        foreach ($post as $k => $v) {
            $_k = $key ? $key . '[' . $k . ']' : $k;
            if (is_array($v)) {
                $this->formatPostkey($v, $result, $_k);
            } else {
                $result[$_k] = $v;
            }
        }
    }

}
