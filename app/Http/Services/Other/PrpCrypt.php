<?php

namespace App\Http\Services\Other;

use Illuminate\Support\Facades\Log;

class PrpCrypt
{
    public $key;

    public function __construct($k)
    {
        $this->key = base64_decode($k . '=');
    }

    /**
     * 对明文进行加密.
     *
     * @param string $text  需要加密的明文
     * @param mixed  $appid
     *
     * @return array 加密后的密文
     */
    public function encrypt($text, $appid)
    {
        try {
            // 获得16位随机字符串，填充到明文之前
            $random = $this->getRandomStr();
            $text   = $random . pack('N', strlen($text)) . $text . $appid;
            // 网络字节序

            $iv     = substr($this->key, 0, 16);
            // 使用自定义的填充方式对明文进行补位填充
            $pkc_encoder = new Pkcs7Encoder();
            $text        = $pkc_encoder->encode($text);

            $encrypted = openssl_encrypt($text, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);

            // print(base64_encode($encrypted));
            // 使用BASE64对加密后的字符串进行编码
            return [ErrorCode::$OK, base64_encode($encrypted)];
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' in ' . $e->getLine() . ' at ' . $e->getFile());
            // print $e;
            return [ErrorCode::$EncryptAESError, null];
        }
    }

    /**
     * 对密文进行解密.
     *
     * @param string $encrypted 需要解密的密文
     * @param mixed  $appid
     *
     * @return array|string 解密得到的明文
     */
    public function decrypt($encrypted, $appid)
    {
        try {
            // 使用BASE64对需要解密的字符串进行解码
            $ciphertext_dec = base64_decode($encrypted);
            //            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            //
            //            $iv = substr($this->key, 0, 16);
            //            mcrypt_generic_init($module, $this->key, $iv);
            //
            //            //解密
            //            $decrypted = mdecrypt_generic($module, $ciphertext_dec);
            //            mcrypt_generic_deinit($module);
            //            mcrypt_module_close($module);

            $iv = substr($this->key, 0, 16);

            $decrypted = openssl_decrypt($ciphertext_dec, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        } catch (\Exception $e) {
            return [ErrorCode::$DecryptAESError, null];
        }

        try {
            // 去除补位字符
            $pkc_encoder = new Pkcs7Encoder();
            $result      = $pkc_encoder->decode($decrypted);

            // 去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16) {
                return '';
            }
            $content     = substr($result, 16, strlen($result));
            $len_list    = unpack('N', substr($content, 0, 4));
            $xml_len     = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid  = substr($content, $xml_len + 4);
        } catch (\Exception $e) {
            // print $e;
            return [ErrorCode::$IllegalBuffer, null];
        }
        if ($from_appid != $appid) {
            return [ErrorCode::$ValidateAppidError, null];
        }

        return [0, $xml_content];
    }

    /**
     * 随机生成16位字符串.
     *
     * @return string 生成的字符串
     */
    public function getRandomStr()
    {
        $str     = '';
        $str_pol = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max     = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; ++$i) {
            $str .= $str_pol[mt_rand(0, $max)];
        }

        return $str;
    }
}
