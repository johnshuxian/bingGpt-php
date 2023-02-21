<?php
/**
 * created by zhang.
 */

/*
 * 获取唯一UUID，防止重复
 * @return string
 */
if (!function_exists('getUuid')) {
    function getUuid($separator = '-')
    {
        $str  = md5(uniqid(mt_rand(100, 1000000), true));
        $uuid = substr($str, 0, 8) . $separator;
        $uuid .= substr($str, 8, 4) . $separator;
        $uuid .= substr($str, 12, 4) . $separator;
        $uuid .= substr($str, 16, 4) . $separator;
        $uuid .= substr($str, 20, 12);

        return $uuid;
    }
}

/*
 * 获取随机FORWARDED_IP
 * @return string
 */
if (!function_exists('forwardedIp')) {
    function forwardedIp()
    {
        return '13.' . rand(104, 107) . '.' . rand(0, 255) . '.' . rand(0, 255);
    }
}

// 下划线转驼峰
if (!function_exists('convertToCamel')) {
    function convertToCamel($name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }
}

// 小驼峰转下划线
if (!function_exists('convertToUnderline')) {
    function convertToUnderline($name): array|string
    {
        return str_replace(' ', '_', strtolower(trim(preg_replace('/([A-Z])/', ' $1', $name))));
    }
}

// 数组键名驼峰与下划线互转
if (!function_exists('dataConvert')) {
    function dataConvert($array, $toUnderLine = true): array
    {
        $temp = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = dataConvert($value, $toUnderLine);
            }

            if (is_object($value)) {
                $value = dataConvert($value->toArray(), $toUnderLine);
            }

            $temp[$toUnderLine ? convertToUnderline($key) : convertToCamel($key)] = $value;
        }

        return $temp;
    }
}
