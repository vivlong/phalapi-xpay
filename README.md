# PhalApi 2.x 的第三方支付扩展
PhalApi 2.x扩展类库，含微信支付和支付宝支付，支持APP支付。

## 安装和配置
修改项目下的composer.json文件，并添加：  
```
    "vivlong/phalapi-xpay":"dev-master"
```
然后执行```composer update```。  

安装成功后，添加以下配置到/path/to/phalapi/config/app.php文件：  
```php
    /**
     * 支付相关配置
     */
    'Xpay' =>  array(
        'alipayapp' => array(
            'appId'             => '<yourAppId>',
            'rsa_privateKey'    => '<yourRsaPrivateKey>',
            'rsa_publicKey'     => '<yourRsaPublicKey>',
            'notify_url'        => '<yourNotifyUrl>'
        ),
        'wechatpayapp' => array(
            'app_id'            => '<yourAppId>',
            'mch_id'            => '<yourMCHId>',
            'app_key'           => '<yourAppKey>',
            'notify_url'        => '<yourNotifyUrl>',
        )
    ),
```
并根据自己的情况修改填充。 

## 注册
在/path/to/phalapi/config/di.php文件中，注册：  
```php
$di->xpay = function() {
        return new \PhalApi\Xpay\Lite();
};
```

## 使用
请求使用方式：
```php
    $di = \PhalApi\DI();
    $xpay = $di->xpay;
    $xpay->set('wechatpayapp');
    $content = 'XX APP';
    $attach = '';
    echo $xpay->getPrepayid($order_no, $order_amount, $content, $attach);

    $di = \PhalApi\DI();
    $xpay = $di->xpay;
    $xpay->set('alipayapp');
    $title = 'XX APP';
    $subject = '';
    $passback_params = '';
    echo $xpay->getOrderString($this->order_no, $this->order_amount, $title, $subject, $passback_params);
```  

Notify回调使用方式：
```php
    $di = \PhalApi\DI();
    $xpay = $di->xpay;
    $xpay->set($this->type);
    $notify = $GLOBALS['PAY_NOTIFY'];
    if(!$notify) {
        $di->logger->log('Xpay','Not data commit', array('Type' => $this->type));
        exit; //直接结束程序，不抛出错误
    }
    //验证
    if($xpay->verifyNotify($notify) == true){
        //获取订单信息
        //$info = $xpay->getInfo();
        //TODO 更新对应的订单信息,返回布尔类型
        $res = true;
        //订单更新成功
        if($res){
            if ($this->method == "return") {
                //TODO 同步回调需要跳转的页面
            } else {
                $di->logger->log('Xpay', 'Pay Success', array('Type' => $this->type, 'Method' => $this->method, 'Data'=> $info));
                //移除超全局变量
                unset($GLOBALS['PAY_NOTIFY']);
                //支付接口需要返回的信息，通知接口我们已经接收到了支付成功的状态
                $xpay->notifySuccess();
                exit; //需要结束程序
            }
        }else{
            $xpay->notifyError();
            $di->logger->log('Xpay','Failed to pay');
            exit;
        }
    }else{
        $xpay->notifyError();
        $di->logger->log('Xpay','Verify error', array('Type' => $this->type, 'Method'=> $this->method, 'Data' => $notify));
        exit;
    }
```  

