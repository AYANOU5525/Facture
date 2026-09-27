<?php

session_start();
require_once '../vendor/autoload.php';
require_once '../includes/csrf.php';
require_once '../config/db.php';
require_once '../includes/b2b_helpers.php';

use App\Controllers\AuthController;

(new AuthController($pdo))->confirmEmail();
