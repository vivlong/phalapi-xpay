<?php
//写入超全局变量
$GLOBALS['PAY_NOTIFY'] = $GLOBALS['HTTP_RAW_POST_DATA'];
$_REQUEST['service'] = 'App.Xpay.getNotify';
$_REQUEST['type']	= 'wechatpayapp';
$_REQUEST['method'] = 'notify';
require_once(dirname(dirname(dirname(__FILE__))) . '/index.php');