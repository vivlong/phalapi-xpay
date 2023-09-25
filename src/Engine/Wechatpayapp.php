<?php

namespace PhalApi\Xpay\Engine;

use PhalApi\Xpay\Base;

class Wechatpayapp extends Base
{
    protected $config;

    //请求后返回的参数
    protected $values = [];

    /**
     * 配置检查.
     *
     * @return [type] [description]
     */
    public function check()
    {
        $di = \PhalApi\DI();
        if (!$this->config['app_id'] || !$this->config['mch_id'] || !$this->config['app_key']) {
            $di->logger->log(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, 'setting error');

            return false;
        }

        return true;
    }

    /**
     * getPrepayid.
     *
     * @return string
     */
    public function getPrepayid($order_no, $order_amount, $content, $attach)
    {
        $di = \PhalApi\DI();
        try {
            $app_id = $this->config['app_id'];
            $mch_id = $this->config['mch_id'];
            $notify_url = $this->config['notify_url'];
            $prepay_id = $this->generatePrepayId($app_id, $mch_id, $notify_url, $order_no, $order_amount, $content, $attach);
            $di->logger->debug(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['prepay_id' => $prepay_id]);
            if ($prepay_id) {
                $response = [
                    'appid' => $app_id,
                    'partnerid' => $mch_id,
                    'prepayid' => $prepay_id,
                    'package' => 'Sign=WXPay',
                    'noncestr' => $this->createNoncestr(),
                    'timestamp' => time(),
               ];
                $response['sign'] = $this->getSign($response);
                $di->logger->debug(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Response' => $response]);

                return $response;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $di->logger->error(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * 请求验证
     */
    public function verifyNotify($notify)
    {
        $di = \PhalApi\DI();
        $this->values = $this->xmlToArray($notify);
        if ('SUCCESS' != $this->values['return_code']) {
            $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['支付失败' => $this->values]);

            return false;
        }
        if (!$this->checkSign()) {
            $di->logger->info(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['签名错误' => $this->values]);

            return false;
        }
        //写入订单信息
        $this->setInfo($this->values);

        return true;
    }

    /**
     * 异步通知验证成功返回信息.
     */
    public function notifySuccess()
    {
        $return = [];
        $return['return_code'] = 'SUCCESS';
        $return['return_msg'] = 'OK';
        echo $this->arrayToXml($return);
    }

    /**
     * 异步通知验证失败返回信息.
     *
     * @return [type] [description]
     */
    public function notifyError()
    {
        $return = [];
        $return['return_code'] = 'FAILED';
        $return['return_msg'] = 'FAILED';
        echo $this->arrayToXml($return);
    }

    private function generatePrepayId($app_id, $mch_id, $notify_url, $order_no, $order_amount, $body, $attach)
    {
        $di = \PhalApi\DI();
        $params = [
            'appid' => $app_id,
            'mch_id' => $mch_id,
            'nonce_str' => $this->createNoncestr(),
            'body' => $body,
            'out_trade_no' => $order_no,
            'total_fee' => $order_amount,
            'spbill_create_ip' => \PhalApi\Tool::getClientIp(),
            'notify_url' => $notify_url,
            'trade_type' => 'APP',
            'limit_pay' => 'no_credit',
            'attach' => urlencode($attach),
        ];
        $params['sign'] = $this->getSign($params);
        $di->logger->debug(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Params' => $params]);
        $xml = $this->getXMLFromArray($params);
        $curl = new \PhalApi\CUrl(1);
        $curl->setHeader(['Content-Type: text/xml']);
        $response = $curl->post('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        $this->values = $this->xmlToArray($response);
        $di->logger->debug(__NAMESPACE__.DIRECTORY_SEPARATOR.__CLASS__.DIRECTORY_SEPARATOR.__FUNCTION__, ['Response' => $this->values]);
        if (array_key_exists('return_code', $this->values) && 'SUCCESS' == $this->values['return_code']) {
            if (array_key_exists('result_code', $this->values) && 'SUCCESS' == $this->values['result_code']) {
                if (array_key_exists('prepay_id', $this->values)) {
                    return (string) $this->values['prepay_id'];
                }
            }
        }

        return false;
    }

    /**
     *  产生随机字符串，不长于32位.
     */
    private function createNoncestr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        for ($i = 0; $i < $length; ++$i) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }

        return $str;
    }

    private function getXMLFromArray($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= sprintf('<%s>%s</%s>', $key, $val, $key);
            } else {
                $xml .= sprintf('<%s><![CDATA[%s]]></%s>', $key, $val, $key);
            }
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * 检测签名.
     */
    protected function checkSign()
    {
        if (!array_key_exists('sign', $this->values)) {
            return false;
        }
        $sign = $this->getSign($this->values);
        if ($this->values['sign'] == $sign) {
            return true;
        }

        return false;
    }

    /**
     *  生成签名.
     */
    private function getSign($data)
    {
        //第一步：对参数按照key=value的格式，并按照参数名ASCII字典序排序如下：
        ksort($data);
        $string = $this->toUrlParams($data);
        //第二步：拼接API密钥
        $string = $string.'&key='.$this->config['app_key'];
        //MD5加密
        $string = md5($string);
        //将得到的字符串全部大写并返回
        return strtoupper($string);
    }

    /**
     * 拼接签名字符串.
     *
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function toUrlParams($urlObj)
    {
        $buff = '';
        foreach ($urlObj as $k => $v) {
            if ('sign' != $k && '' !== $v) {
                $buff .= $k.'='.$v.'&';
            }
        }
        $buff = trim($buff, '&');

        return $buff;
    }

    /**
     * 	array转xml.
     */
    private function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml = $xml.'<'.$key.'>'.$val.'</'.$key.'>';
            } else {
                $xml = $xml.'<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
            }
        }
        $xml = $xml.'</xml>';

        return $xml;
    }

    /**
     * 将xml转为array.
     *
     * @param string $xml
     *
     * @throws WxPayException
     */
    public function xmlToArray($xml)
    {
        if (!$xml) {
            return;
        }
        //将XML转为array
        //禁止引用外部xml实体
        $disableLibxmlEntityLoader = libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        libxml_disable_entity_loader($disableLibxmlEntityLoader);

        return $values;
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
        $info['status'] = ('SUCCESS' == $notify['return_code']) ? true : false;
        $info['money'] = $notify['total_fee'] / 100;
        //商户订单号
        $info['outTradeNo'] = $notify['out_trade_no'];
        //微信交易号
        $info['tradeNo'] = $notify['transaction_id'];
        $info['customData'] = $notify['attach'];
        $this->info = $info;
    }
}
