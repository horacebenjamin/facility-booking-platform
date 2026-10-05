<?php

use Tests\Support\ConcurrentAttendanceWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentAttendanceWorker::run($argv));
