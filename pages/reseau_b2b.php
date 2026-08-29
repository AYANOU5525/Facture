<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/b2b_helpers.php';

use App\Controllers\ReseauB2BController;

(new ReseauB2BController($pdo))->index();
