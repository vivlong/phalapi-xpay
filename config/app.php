<?php

return array(
    /**
     * 相关配置
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
);
