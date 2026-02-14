<?php
session_start();
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}
echo "Session counter: " . $_SESSION['test_counter'];
echo "\nSession file path: " . session_save_path();
