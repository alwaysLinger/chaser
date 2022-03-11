This work comes into being while I learning php multi process coding in my spare time,
of course there would be some thoughtless bugs, so please do not use this in your production
environment, it's basically just a multi processes tcp framework for now if you allow me.
But still if you want to know anything about this work, feel free to email me at alwayslinger@163.com. 
There are also lots of todos here, if you want to be part of this,you can help me with it.
Next version I will reduce the usage of closure cause it's scope problem kind of tricky for me.

**TODOS**
- daemon mode
- timer support
- multi ports listen
- additional event driver implement
- udp, http, ws and support
- ...

**EXAMPLES**
```php
# test for c10K
# server
php bin/chaser.php start

# 10K clients  just take advantage of swoole coroutine tcp client
php tests/multiclient.php
```