<?php

require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

use App\Controllers\SalesWorkflowController;

(new SalesWorkflowController($pdo))->index();
