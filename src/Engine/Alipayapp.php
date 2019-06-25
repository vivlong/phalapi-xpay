<?php

namespace PhalApi\Xpay\Engine;

use PhalApi\Xpay\Base;

require_once dirname(dirname(__FILE__)).'/SDK/alipay/AopSdk.php';

class Alipayapp extends Base
{
	protected $config;

    /**
     * 配置检查
     * @return [type] [description]
     */
    public function check() {
        if (!$this->config['app_id'] || !$this->config['rsa_privateKey'] || !$this->config['rsa_publicKey']) {
            \PhalApi\DI()->logger->log('Xpay','Alipayapp setting error');
            return false;
        }
        return true;
    }

	/**
     * getOrderString
     * @return string
     */
    public function getOrderString($out_trade_no, $total_amount, $title, $subject, $passback_params, $timeout_express = '15m') {
        try{
            $aop = new \AopClient();
            $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            $aop->appId = $this->config['app_id'];
            $aop->rsaPrivateKey = $this->config['rsa_privateKey'];
            $aop->alipayrsaPublicKey = $this->config['rsa_publicKey'];
            $aop->format = 'json';
            $aop->charset = 'UTF-8';
            $aop->signType = 'RSA2';
            $request = new \AlipayTradeAppPayRequest();
            $bizObj = array(
                'body' => strval($title),
                'subject' => strval($subject),
                'out_trade_no' => strval($out_trade_no),
                'total_amount' => strval($total_amount),
                'product_code' => 'QUICK_MSECURITY_PAY',
                'goods_type' => strval(0),
                'timeout_express' => strval($timeout_express),
                'passback_params' => urlencode($passback_params),
            );
            $bizcontent = json_encode($bizObj);
            $request->setNotifyUrl($this->config['notify_url']);
            $request->setBizContent($bizcontent);
            $response = $aop->sdkExecute($request);
            return $response;
        } catch(Exception $e) {
            \PhalApi\DI()->logger->error('Xpay\Alipayapp getOrderString', $e->getMessage());
            return false;
        }
    }

    /**
     * 请求验证
     * @param  [type] $notify [description]
     * @return [type]         [description]
     */
    public function verifyNotify($notify) {
        if (!empty($notify['notify_time']) && !empty($notify['notify_type']) && !empty($notify['notify_id']) && !empty($notify['app_id']) && !empty($notify['out_trade_no'])) {
            $aop = new AopClient();
            $aop->alipayrsaPublicKey = $this->config['rsa_publicKey'];
            $flag = $aop->rsaCheckV1($notify, null, 'RSA2');
            if ($flag) {
                $this->setInfo($notify);
                return true;
            } else {
                \PhalApi\DI()->logger->error('Xpay\Alipayapp verifyNotify\ rsaCheckV1 error');
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * 写入订单信息
     * @param [type] $notify [description]
     */
    protected function setInfo($notify) {
        $info = array();
        //支付状态
        $info['status'] = ($notify['trade_status'] == 'TRADE_FINISHED' || $notify['trade_status'] == 'TRADE_SUCCESS') ? true : false;
        $info['money'] = $notify['total_fee'];
        //商户订单号
        $info['outTradeNo'] = $notify['out_trade_no'];
        //支付宝交易号
        $info['tradeNo'] = $notify['trade_no'];
        $info['customData'] = $notify['passback_params'];
        $this->info = $info;
    }

}