<?php

namespace PhalApi\Xpay\Engine;

require_once dirname(dirname(__FILE__)).'/SDK/alipay/AopSdk.php';

class Alipayapp
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
            $aop->format = 'json';
            $aop->charset = 'UTF-8';
            $aop->signType = 'RSA2';
            $aop->alipayrsaPublicKey = $this->config['rsa_publicKey'];
            $request = new \AlipayTradeAppPayRequest();
            $bizObj = array(
                'body' => strval($title),
                'subject' => strval($subject),
                'out_trade_no' => strval($out_trade_no),
                'total_amount' => strval($total_amount),
                'product_code' => 'QUICK_MSECURITY_PAY',
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
                return true;
            } else {
                //SeasLog::log(SEASLOG_ERROR, 'rsaCheckV1 error');
                return true;
            }
        } else {
            return false;
        }
    }

}