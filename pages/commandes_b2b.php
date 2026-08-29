<?php

require_once '../includes/auth.php';
require_once '../vendor/autoload.php';
require_once '../includes/b2b_helpers.php';

use App\Controllers\CommandeB2BController;

(new CommandeB2BController($pdo))->index();
