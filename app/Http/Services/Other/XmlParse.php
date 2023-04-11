<?php

namespace App\Http\Services\Other;

use Illuminate\Support\Facades\Log;

/**
 * XMLParse class.
 *
 * 提供提取消息格式中的密文及生成回复消息格式的接口.
 */
class XmlParse
{
    /**
     * 提取出xml数据包中的加密消息.
     *
     * @param array $data 待提取的xml字符串
     *
     * @return array 提取出的加密消息字符串
     */
    public function extract($data): array
    {
        try {
            $encrypt = $data['Encrypt'];

            $to_user_name = $data['ToUserName'];

            return [0, $encrypt, $to_user_name];
        } catch (\Exception $e) {
            // print $e . "\n";
        }

        libxml_disable_entity_loader(true);

        try {
            $xml = new \DOMDocument();
            $xml->loadXML($data);
            $array_e      = $xml->getElementsByTagName('Encrypt');
            $array_a      = $xml->getElementsByTagName('ToUserName');
            $encrypt      = $array_e->item(0)->nodeValue;
            $to_user_name = $array_a->item(0)->nodeValue;

            return [0, $encrypt, $to_user_name];
        } catch (\Exception $e) {
            Log::channel('wx')->info($e->getMessage());
            // print $e . "\n";
            return [ErrorCode::$ParseXmlError, null, null];
        }
    }

    /**
     * 生成xml消息.
     *
     * @param string $encrypt   加密后的消息密文
     * @param string $signature 安全签名
     * @param string $timestamp 时间戳
     * @param string $nonce     随机字符串
     */
    public function generate($encrypt, $signature, $timestamp, $nonce)
    {
        $format = '<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>';

        return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
    }
}
