<?php

use Tests\Support\ConcurrentNotificationWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentNotificationWorker::run($argv));
