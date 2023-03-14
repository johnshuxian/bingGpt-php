<?php
/**
 * created by zhang.
 */
$ip = forwardedIp();

return [
    'cookie' => '',
    'headers'=> [
        'accept'                     => 'application/json',
        'accept-language'            => 'en-US,en;q=0.9',
        'content-type'               => 'application/json',
        'sec-ch-ua'                  => '"Not_A Brand";v="99", "Microsoft Edge";v="109", "Chromium";v="109"',
        'sec-ch-ua-arch'             => 'x86',
        'sec-ch-ua-bitness'          => '64',
        'sec-ch-ua-full-version'     => '110.0.1587.69',
        'sec-ch-ua-full-version-list'=> '"Chromium";v="110.0.5481.192", "Not A(Brand";v="24.0.0.0", "Microsoft Edge";v="110.0.1587.69"',
        'sec-ch-ua-mobile'           => '?0',
        'sec-ch-ua-model'            => '',
        'sec-ch-ua-platform'         => 'Windows',
        'sec-ch-ua-platform-version' => '15.0.0',
        'sec-fetch-dest'             => 'empty',
        'sec-fetch-mode'             => 'cors',
        'sec-fetch-site'             => 'same-origin',
        'x-ms-client-request-id'     => getUuid(),
        'x-ms-useragent'             => 'azsdk-js-api-client-factory/1.0.0-beta.1 core-rest-pipeline/1.10.0 OS/Win32',
        //        'Referer'                    => 'https=>//www.bing.com/search?q=Bing+AI&showconv=1&FORM=hpcodx',
        'Referrer-Policy'            => 'origin-when-cross-origin',
        'x-forwarded-for'            => $ip,
    ]
];
