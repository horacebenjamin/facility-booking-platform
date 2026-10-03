<?php

use Tests\Support\ConcurrentPaymentWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentPaymentWorker::run($argv));
