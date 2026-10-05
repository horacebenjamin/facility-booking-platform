<?php

use Tests\Support\ConcurrentClosureWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentClosureWorker::run($argv));
