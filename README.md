laravel-bingGpt-api

看到bing ai也出来了，终于申请到了，于是花了点时间逆向了下bing的api和websocket，平时用laravel比较多，就顺便简单写了下，目前只做了两个接口，但是核心功能也完成了
需要在config/bing.php中填写上自己的cookie

'cookie' =>''

配置好env后和数据库后，执行php artisan migrate


接口如下：

GET  /api/conversation/creat

返回内容：

`{
"status": "success",
"code": 200,
"message": "操作成功",
"data": {
"conversationId": "51D|XXXXX",
"clientId": "10555217XXXX",
"conversationSignature": "DXXXX",
"chatId": 1
},
"error": null
}`

POST /api/conversation/ask

需要上面接口中的chatId

参数：
`{
"prompt":"提问的艺术",
"chatId":1
}`

返回内容:

`{
"status": "success",
"code": 200,
"message": "操作成功",
"data": {
"ask": "提问的艺术",
"answer": "你好，这是必应。提问的艺术是一本教你如何通过富有技巧性的提问来提高沟通效率并提升自身影响力的书[^1^] [^2^]。它强调巧妙提问远比给出答案更为重要[^1^] [^3^]。本书用具体的问题、真实的案例，为读者打造了一个提升提问技巧的实用宝典[^1^] [^2^]。",
"adaptive_cards": "[1]: https://book.douban.com/subject/25806793/ "提问的艺术 (豆瓣)""
},
"error": null
}`
