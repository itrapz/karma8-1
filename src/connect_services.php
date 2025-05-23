<?php
$servername = getenv('MYSQL_HOST');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

const DEFAULT_QUEUE = 'job_queue';

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("MySQL Connection failed: " . $conn->connect_error);
}

$redis = new Redis();
$redis->connect('redis', 6379);
