<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/db.php';

if ($conn) {
    echo "Kết nối database thành công!";
} else {
    echo "Kết nối thất bại!";
}
?>