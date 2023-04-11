<?php

namespace App\Http\Services\Other;

class Pkcs7Encoder
{
    public static $block_size = 32;

    /**
     * 对需要加密的明文进行填充补位.
     *
     * @param $text:需要进行填充补位操作的明文
     *
     * @return string
     */
    public function encode($text)
    {
        $text_length = strlen($text);
        // 计算需要填充的位数
        $amount_to_pad = self::$block_size - ($text_length % self::$block_size);
        if (0 == $amount_to_pad) {
            $amount_to_pad = self::$block_size;
        }
        // 获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp     = '';
        for ($index = 0; $index < $amount_to_pad; ++$index) {
            $tmp .= $pad_chr;
        }

        return $text . $tmp;
    }

    /**
     * 对解密后的明文进行补位删除.
     *
     * @param mixed $text
     *
     * @return false|string :删除填充补位后的明文
     */
    public function decode($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }

        return substr($text, 0, strlen($text) - $pad);
    }
}
