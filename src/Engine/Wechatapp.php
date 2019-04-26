<?php

namespace PhalApi\Xpay\Engine;

use PhalApi\Xpay\Base;

class Wechatpayapp extends Base
{
    protected $config;

	//请求后返回的参数
	protected $values = array();

    public function __construct($config) {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 配置检查
     * @return [type] [description]
     */
    public function check() {
        if (!$this->config['app_id'] || !$this->config['mch_id'] || !$this->config['app_key']) {
            \PhalApi\DI()->logger->log('Xpay','Wechatpayapp setting error');
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
        try {
            $app_id = $this->config['app_id'];
            $mch_id = $this->config['mch_id'];
            $app_key = $this->config['app_key'];
            $notify_url = $this->config['notify_url'];
            $prepay_id = $this->generatePrepayId($app_id, $mch_id, $app_key, $notify_url, $order_no, $order_amount, $content, $attach);
            $response = array(
                'appid' => $app_id,
                'partnerid' => $mch_id,
                'prepayid' => $prepay_id,
                'package' => 'Sign=WXPay',
                'noncestr' => $this->generateNonce(),
                'timestamp' => time(),
          );
            $response['sign'] = $this->calculateSign($response, $app_key);
            \PhalApi\DI()->logger->debug(json_encode($response));
            return $response;
        } catch (Exception $e) {
            \PhalApi\DI()->logger->error('Xpay\ getPrepayid', $e->getMessage());
            return false;
        }
    }

    /**
     * 请求验证
     */
    public function verifyNotify($notify) {
    	$this->values = $this->xmlToArray($notify);
		if($this->values['return_code'] != 'SUCCESS'){
			\PhalApi\DI()->logger->log('Xpay','支付失败', $this->values);
			return false;
		}
		if(!$this->checkSign()){
			\PhalApi\DI()->logger->log('Xpay','签名错误', $this->values);
			return false;
		}
		//写入订单信息
		$this->setInfo($this->values);
		return true;
    }

    /**
     * 异步通知验证成功返回信息
     */
    public function notifySuccess(){
    	$return = array();
    	$return['return_code'] = 'SUCCESS';
    	$return['return_msg'] = 'OK';
    	echo $this->arrayToXml($return);
    }

    /**
     * 异步通知验证失败返回信息
     * @return [type] [description]
     */
    public function notifyError(){
        $return = array();
    	$return['return_code'] = 'FAILED';
    	$return['return_msg'] = 'FAILED';
    	echo $this->arrayToXml($return);
    }

    private function generatePrepayId($app_id, $mch_id, $app_key, $notify_url, $order_no, $order_amount, $body, $attach)
    {
        $params = array(
            'appid' => $app_id,
            'mch_id' => $mch_id,
            'nonce_str' => $this->generateNonce(),
            'body' => $body,
            'out_trade_no' => $order_no,
            'total_fee' => $order_amount,
            'spbill_create_ip' => PhalApi_Tool::getClientIp(),
            'notify_url' => $notify_url,
            'trade_type' => 'APP',
            'attach' => urlencode($attach),
        );
        $params['sign'] = $this->calculateSign($params, $app_key);
        $xml = $this->getXMLFromArray($params);
        $curl = new \PhalApi\CUrl(1);
        $curl->setHeader(array('Content-Type: text/xml'));
        $result = $curl->post('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        $xml = simplexml_load_string($result);
        return (string) $xml->prepay_id;
    }

    private function generateNonce()
    {
        return md5(uniqid('', true));
    }

    private function calculateSign($arr, $key)
    {
        ksort($arr);
        $buff = '';
        foreach ($arr as $k => $v) {
            if ($k != 'sign' && $k != 'key' && $v != '' && !is_array($v)) {
                $buff .= $k.'='.$v.'&';
            }
        }
        $buff = trim($buff, '&');
        return strtoupper(md5($buff.'&key='.$key));
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
	 *
	 * 检测签名
	 */
	protected function checkSign(){
		if(!array_key_exists('sign', $this->values)){
			return false;
		}
		$sign = $this->getSign($this->values);
		if($this->values['sign'] == $sign){
			return true;
		}
		return false;
    }

    /**
     *  生成签名
     */
    private function getSign($data){
        //第一步：对参数按照key=value的格式，并按照参数名ASCII字典序排序如下：
        ksort($data);
        $string = $this->toUrlParams($data);
        //第二步：拼接API密钥
        $string = $string."&key=".$this->config['app_key'];
        //MD5加密
        $string = md5($string);
        //将得到的字符串全部大写并返回
        return strtoupper($string);
    }

    /**
	 *
	 * 拼接签名字符串
	 * @param array $urlObj
	 * @return 返回已经拼接好的字符串
	 */
	private function toUrlParams($urlObj){
		$buff = "";
		foreach ($urlObj as $k => $v)
		{
			if($k != "sign" && $v !== ''){
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}

    /**
     * 	array转xml
     */
    private function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val))
            {
                $xml=$xml."<".$key.">".$val."</".$key.">";

            }
            else
                $xml=$xml."<".$key."><![CDATA[".$val."]]></".$key.">";
        }
        $xml=$xml."</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public function xmlToArray($xml){
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
}
