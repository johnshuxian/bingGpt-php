<?php
/**
 * created by zhang.
 */
$ip = forwardedIp();

return [
    'cookie' => 'MUID=0BF0BD46CEFC6F872059AFFECFCC6E09; _EDGE_V=1; USRLOC=HS=1&CLOC=LAT=30.658976192663737|LON=104.09051730428817|A=733.4464586120832|TS=230220011859|SRC=W; SRCHD=AF=NOFORM; SRCHUID=V=2&GUID=22EDC300D19F4E6DBAC9DAE43F1E03C4&dmnchg=1; SnrOvr=X=rebateson; _UR=QS=0&TQS=0; MicrosoftApplicationsTelemetryDeviceId=e1d576e1-596a-405c-a562-69d860f71121; ANON=A=AF3A64208E2F65BD38369EBDFFFFFFFF&E=1bfc&W=1; NAP=V=1.9&E=1ba2&C=ZyIleKUIu5WJljZnMneXfZF1RIjA_JHg1331BVr0vq47UIBaClh3Ew&W=1; MMCASM=ID=6CA5209DF3364AE090FD7237F8B212A5; ANIMIA=FRE=1; ZHCHATSTRONGATTRACT=TRUE; _EDGE_CD=m=en-us; SUID=A; WLS=C=26e3fbe6b640528e&N=shuxian; _U=1Tc_QrT4KQetb1pHbiqqmBameKavtOFi3txvcnQBwX6RDzV9Dmi2cF3gxG3L5K-o01k8srmpw3H4yLMSa15etNzmER2Pxw0lt3lNuuGZ_rTA0ezAmoejjOtmkH3YDnzO9xqstKzWI-qOnLkJpAsB1Ig1RZOTFrpdK0eJKFBqLBgSmadr7KulVrH859VocEdnYJUkimKFyMU3sonRXQvMgia5gbdCtv1Oo0wCdFqinz70; MSCC=1; SRCHUSR=DOB=20230214&T=1676855934000; _HPVN=CS=eyJQbiI6eyJDbiI6MiwiU3QiOjAsIlFzIjowLCJQcm9kIjoiUCJ9LCJTYyI6eyJDbiI6MiwiU3QiOjAsIlFzIjowLCJQcm9kIjoiSCJ9LCJReiI6eyJDbiI6MiwiU3QiOjAsIlFzIjowLCJQcm9kIjoiVCJ9LCJBcCI6dHJ1ZSwiTXV0ZSI6dHJ1ZSwiTGFkIjoiMjAyMy0wMi0yMFQwMDowMDowMFoiLCJJb3RkIjowLCJHd2IiOjAsIkRmdCI6bnVsbCwiTXZzIjowLCJGbHQiOjAsIkltcCI6Mzh9; _EDGE_S=SID=3FE9EC3EDB1E675D0617FE80DADE6685&mkt=en-us; ai_session=had+Gym705y4rhe78PIfzV|1676855952596|1676855952596; ipv6=hit=1676859553881&t=4; dsc=order=ShopOrderDefault; _RwBf=r=1&mta=0&rc=203&rb=203&gb=0&rg=0&pc=193&mtu=0&rbb=0.0&g=0&cid=&clo=0&v=4&l=2023-02-19T08:00:00.0000000Z&lft=0001-01-01T00:00:00.0000000&aof=0&o=0&p=bingcopilotwaitlist&c=MY00IA&t=2922&s=2023-02-08T01:49:31.9598182+00:00&ts=2023-02-20T01:27:50.8965201+00:00&rwred=0&wls=2&lka=0&lkt=0&TH=&e=NZp0AB8c3V7ZntqpFP1qIKSXOBFaui2mpZ5aXRn2aenj7u7AZ3fHtn3oSE_gRRRByO14rjtC4refiCkZ37zB7tLPczHlMEAezw63re5AE6Q&A=; _SS=SID=3FE9EC3EDB1E675D0617FE80DADE6685&R=203&RB=203&GB=0&RG=0&RP=193; SRCHHPGUSR=SRCHLANG=zh-Hans&PV=15.0.0&HV=1676855961&BRW=W&BRH=S&CW=1440&CH=411&SCW=1423&SCH=2524&DPR=1.5&UTC=480&DM=0&PRVCW=1440&PRVCH=411&EXLTT=27&BZA=0',
    'end'    => "\x1e",
    'headers'=> [
        'accept'                     => 'application/json',
        'accept-language'            => 'en-US,en;q=0.9',
        'content-type'               => 'application/json',
        'sec-ch-ua'                  => '"Not_A Brand";v="99", "Microsoft Edge";v="109", "Chromium";v="109"',
        'sec-ch-ua-arch'             => '"x86"',
        'sec-ch-ua-bitness'          => '"64"',
        'sec-ch-ua-full-version'     => '"109.0.1518.78"',
        'sec-ch-ua-full-version-list'=> '"Not_A Brand";v="99.0.0.0", "Microsoft Edge";v="109.0.1518.78", "Chromium";v="109.0.5414.120"',
        'sec-ch-ua-mobile'           => '?0',
        'sec-ch-ua-model'            => '',
        'sec-ch-ua-platform'         => '"Windows"',
        'sec-ch-ua-platform-version' => '"15.0.0"',
        'sec-fetch-dest'             => 'empty',
        'sec-fetch-mode'             => 'cors',
        'sec-fetch-site'             => 'same-origin',
        'x-ms-client-request-id'     => getUuid(),
        'x-ms-useragent'             => 'azsdk-js-api-client-factory/1.0.0-beta.1 core-rest-pipeline/1.10.0 OS/Win32',
        'Referer'                    => 'https=>//www.bing.com/search?q=Bing+AI&showconv=1&FORM=hpcodx',
        'Referrer-Policy'            => 'origin-when-cross-origin',
        'x-forwarded-for'            => $ip,
    ],
    'wss_headers'=> [
        //        'Http'                    => '1.1',
        //        'Host'                    => 'sydney.bing.com',
        'Connection'              => 'Upgrade',
        //        'Pragma'                  => 'no-cache',
        //        'Cache-Control'           => 'no-cache',
        'User-Agent'              => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36 Edg/110.0.1587.49',
        'Upgrade'                 => 'websocket',
        'Origin'                  => 'https://www.bing.com',
        'Sec-WebSocket-Version'   => '13',
        //        'Accept-Encoding'         => 'gzip, deflate, br',
        //        'Accept-Language'         => 'zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
        'x-forwarded-for'         => $ip,
        //        'Sec-WebSocket-Key'       => 'JIPdqQ3KJyJYoG03x09vUg==',
        'Sec-WebSocket-Extensions'=> 'permessage-deflate; client_max_window_bits',
    ],
];
