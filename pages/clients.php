<?php

require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

use App\Controllers\ClientController;

(new ClientController($pdo))->index();
