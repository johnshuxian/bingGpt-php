<?php

namespace App\Http\Services\Other;

class MsgCrypt
{
    private $token;
    private $encodingAesKey;
    private $appId;

    /**
     * 构造函数.
     *
     * @param $token string 公众平台上，开发者设置的token
     * @param $encodingAesKey string 公众平台上，开发者设置的EncodingAESKey
     * @param $appId string 公众平台的appId
     */
    public function __construct($token, $encodingAesKey, $appId)
    {
        $this->token          = $token;
        $this->encodingAesKey = $encodingAesKey;
        $this->appId          = $appId;
    }

    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * <ol>
     *    <li>利用收到的密文生成安全签名，进行签名验证</li>
     *    <li>若验证通过，则提取xml中的加密消息</li>
     *    <li>对消息进行解密</li>
     * </ol>.
     *
     * @param $msgSignature string 签名串，对应URL参数的msg_signature
     * @param $timestamp string 时间戳 对应URL参数的timestamp
     * @param $nonce string 随机串，对应URL参数的nonce
     * @param $postData array 密文，对应POST请求的数据
     * @param &$msg string 解密后的原文，当return返回0时有效
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptMsg($msgSignature, $timestamp = null, $nonce, $postData, &$msg)
    {
        if (43 != strlen($this->encodingAesKey)) {
            return ErrorCode::$IllegalAesKey;
        }

        $pc = new PrpCrypt($this->encodingAesKey);

        // 提取密文
        $xml_parse = new XmlParse();

        $array = $xml_parse->extract($postData);
        $ret   = $array[0];

        if (0 != $ret) {
            return $ret;
        }

        if (null == $timestamp) {
            $timestamp = time();
        }

        $encrypt     = $array[1];
        $touser_name = $array[2];

        // 验证安全签名
        $sha1  = new Sha1();
        $array = $sha1->getSHA1($this->token, $timestamp, $nonce, $encrypt);

        $ret   = $array[0];

        if (0 != $ret) {
            return $ret;
        }

        $signature = $array[1];

        if ($signature != $msgSignature) {
            return ErrorCode::$ValidateSignatureError;
        }

        $result = $pc->decrypt($encrypt, $this->appId);
        if (0 != $result[0]) {
            return $result[0];
        }
        $msg = $result[1];

        return ErrorCode::$OK;
    }
}
