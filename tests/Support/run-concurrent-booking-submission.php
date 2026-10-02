<?php

use Tests\Support\ConcurrentBookingSubmissionWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentBookingSubmissionWorker::run($argv));
