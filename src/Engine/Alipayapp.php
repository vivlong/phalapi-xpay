<?php

namespace PhalApi\Xpay\Engine;

use PhalApi\Xpay\Base;
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use Alipay\EasySDK\Kernel\Config;

// require_once dirname(dirname(__FILE__)).'/SDK/alipay/AopSdk.php';

class Alipayapp extends Base
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
        Factory::setOptions($this->getOptions());
    }

    /**
     * 配置检查.
     *
     * @return [type] [description]
     */
    public function check()
    {
        $di = \PhalApi\DI();
        if (!$this->config['app_id'] || !$this->config['rsa_privateKey'] || !$this->config['rsa_publicKey']) {
            $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, 'setting error');

            return false;
        }

        return true;
    }

    private function getOptions()
    {
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';
        $options->appId = $this->config['app_id'];
        // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
        // $options->merchantPrivateKey = '<-- 请填写您的应用私钥，例如：MIIEvQIBADANB ... ... -->';
        // $options->alipayCertPath = '<-- 请填写您的支付宝公钥证书文件路径，例如：/foo/alipayCertPublicKey_RSA2.crt -->';
        // $options->alipayRootCertPath = '<-- 请填写您的支付宝根证书文件路径，例如：/foo/alipayRootCert.crt" -->';
        // $options->merchantCertPath = '<-- 请填写您的应用公钥证书文件路径，例如：/foo/appCertPublicKey_2019051064521003.crt -->';
        //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
        $options->alipayPublicKey = $this->config['rsa_publicKey'];
        //可设置异步通知接收服务地址（可选）
        $options->notifyUrl = $this->config['notify_url'];
        //可设置AES密钥，调用AES加解密相关接口时需要（可选）
        // $options->encryptKey = "<-- 请填写您的AES密钥，例如：aa4BtZ4tspm2wnXLb1ThQA== -->";

        return $options;
    }

    /**
     * getOrderString.
     *
     * @return string
     */
    public function getOrderString($out_trade_no, $total_amount, $title, $subject, $passback_params, $timeout_express = '15m')
    {
        $di = \PhalApi\DI();
        try {
            // $aop = new \AopClient();
            // $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
            // $aop->appId = $this->config['app_id'];
            // $aop->rsaPrivateKey = $this->config['rsa_privateKey'];
            // $aop->alipayrsaPublicKey = $this->config['rsa_publicKey'];
            // $aop->format = 'json';
            // $aop->charset = 'UTF-8';
            // $aop->signType = 'RSA2';
            // $request = new \AlipayTradeAppPayRequest();
            // $bizObj = [
            //     'body' => strval($title),
            //     'subject' => strval($subject),
            //     'out_trade_no' => strval($out_trade_no),
            //     'total_amount' => strval($total_amount),
            //     'product_code' => 'QUICK_MSECURITY_PAY',
            //     'goods_type' => strval(0),
            //     'timeout_express' => strval($timeout_express),
            //     'passback_params' => urlencode($passback_params),
            // ];
            // $bizcontent = json_encode($bizObj);
            // $request->setNotifyUrl($this->config['notify_url']);
            // $request->setBizContent($bizcontent);
            // $response = $aop->sdkExecute($request);

            // EasySDK
            // https://opendocs.alipay.com/open/02e7gq?scene=20
            $result = Factory::payment()->app()->asyncNotify($this->config['notify_url'])->pay(strval($subject), strval($out_trade_no), strval($total_amount));
            $responseChecker = new ResponseChecker();
            if ($responseChecker->success($result)) {
                // $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Exception' => $e->getMessage()]);
            } else {
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Msg' => $result->msg, 'SubMsg' => $result->subMsg]);
            }

            return $result;
        } catch (\Exception $e) {
            $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * 请求验证
     *
     * @param [type] $notify [description]
     *
     * @return [type] [description]
     */
    public function verifyNotify($notify)
    {
        $di = \PhalApi\DI();
        if (!empty($notify['notify_time']) && !empty($notify['notify_type']) && !empty($notify['notify_id']) && !empty($notify['app_id']) && !empty($notify['out_trade_no'])) {
            $aop = new \AopClient();
            $aop->alipayrsaPublicKey = $this->config['rsa_publicKey'];
            $flag = $aop->rsaCheckV1($notify, null, 'RSA2');
            if ($flag) {
                $this->setInfo($notify);

                return true;
            } else {
                $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, 'rsaCheckV1 error');

                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * 写入订单信息.
     *
     * @param [type] $notify [description]
     */
    protected function setInfo($notify)
    {
        $info = [];
        //支付状态
        $info['status'] = ('TRADE_FINISHED' == $notify['trade_status'] || 'TRADE_SUCCESS' == $notify['trade_status']) ? true : false;
        $info['money'] = $notify['total_fee'];
        //商户订单号
        $info['outTradeNo'] = $notify['out_trade_no'];
        //支付宝交易号
        $info['tradeNo'] = $notify['trade_no'];
        $info['customData'] = $notify['passback_params'];
        $this->info = $info;
    }
}
