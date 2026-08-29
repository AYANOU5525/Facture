<?php

require_once '../includes/auth.php';
require_once '../vendor/autoload.php';
require_once '../includes/b2b_helpers.php';

use App\Controllers\TeamController;

(new TeamController($pdo))->index();
