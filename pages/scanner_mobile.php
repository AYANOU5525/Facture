<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use App\Controllers\ScannerMobileController;

(new ScannerMobileController($pdo))->index();
