<?php

use Tests\Support\ConcurrentInvoiceWorker;

require __DIR__.'/../../vendor/autoload.php';

exit(ConcurrentInvoiceWorker::run($argv));
