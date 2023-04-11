<?php

namespace App\Http\Services\Other;

use App\Http\Services\BaseService;

class WxService extends BaseService
{
    protected $token;

    protected $key;

    /**
     * @var mixed
     */
    private $appid;

    /**
     * @var mixed
     */
    private $secret;

    public function __construct($appid = '', $token = '', $key = '')
    {
        $this->appid      = $appid;
//        $this->secret     = $secret;
        $this->token      = $token;
        $this->key        = $key;
        parent::__construct();
    }

    /**
     * 签名校验.
     *
     * @param mixed $signature
     * @param mixed $timestamp
     * @param mixed $nonce
     *
     * @return bool
     */
    public function checkSignature($signature, $timestamp, $nonce)
    {
        $token = $this->token;

        $tmpArr = [$token, $timestamp, $nonce];

        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        }

        return false;
    }

    /**
     * 解密.
     *
     * @param mixed $message
     */
    public function decryptMsg($message): array
    {
        $msg    = '';
        $return = (new MsgCrypt($this->token, $this->key, $this->appid))->decryptMsg(
            $message['msg_signature'],
            $message['timestamp'],
            $message['nonce'],
            $message['xml'] ?? $message,
            $msg
        );

        if (isset($message['xml'])) {
            $msg = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);

            $msg = json_encode($msg);
        }

        return [$return, json_decode($msg, true)];
    }
}
