WebServer with PHP extension
=== 
* httpparser: A PHP extension for the C http parser from Ruby's Mongrel web server.  
* swoole: PHP's asynchronous & concurrent & distributed networking framework.


Installation
---

swoole extension

```
pecl install swoole
```

http_parser extension
```
cd ext
phpize
./configure
make
sudo make install
```

extension=swoole.so
extension=httpparser.so

Run
---
```
php webserver.php
```

Credits
--- 

The http parser is from Mongrel http://mongrel.rubyforge.org by Zed Shaw.
Mongrel Web Server (Mongrel) is copyrighted free software by Zed A. Shaw
<zedshaw at zedshaw dot com> You can redistribute it and/or modify it under
either the terms of the GPL.

The swoole is from <http://pecl.php.net/package/swoole>.
