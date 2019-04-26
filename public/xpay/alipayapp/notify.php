<?php
//写入超全局变量
$GLOBALS['PAY_NOTIFY'] = $_POST ? $_POST : $_GET;
$_REQUEST['service'] = 'App.Xpay.getNotify';
$_REQUEST['type']	= 'alipayapp';
$_REQUEST['method'] = 'notify';
require_once(dirname(dirname(dirname(__FILE__))) . '/index.php');