<?php

use Tests\Support\ConcurrentRecurringBookingSubmissionWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentRecurringBookingSubmissionWorker::run($argv));
