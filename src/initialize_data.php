<?php
const NUM_OF_RECORDS = 1000;
const SUBSCRIPTION_GAP = 60 * 60 * 24 * 3; //3 days
const PERCENT_OF_CONFIRMED_EMAILS = 15;
const PERCENT_OF_REST_SUBSCRIBED = 6.25;

$servername = getenv('MYSQL_HOST');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = 'CREATE DATABASE IF NOT EXISTS ' . $database;
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully";
}

$conn->select_db($database);

$query = '
CREATE TABLE IF NOT EXISTS test.users
    (
        id bigint unsigned auto_increment primary key,
        username  varchar(32)          not null,
        validts   int                  null,
        confirmed tinyint(1) default 0 not null,
        checked   tinyint(1) default 0 not null,
        valid     tinyint(1) default 0 not null,
        email     varchar(320)         not null,
        constraint id unique (id),
        constraint username unique (username),
        constraint email unique (email)
    )';
$stmt = $conn->prepare($query);
$stmt->execute();

$values = [];
$query = 'INSERT INTO users (username, email, confirmed, valid, validts, checked) VALUES %s';

for ($i = 0; $i < NUM_OF_RECORDS; $i++) {

    $isConfirmedEmail = isTrueWithProbability(PERCENT_OF_CONFIRMED_EMAILS);
    $isValidEmail = $isConfirmedEmail ? 1 : (isTrueWithProbability(PERCENT_OF_REST_SUBSCRIBED) ? 1 : 0);
    $validTs = $isValidEmail ? time() + SUBSCRIPTION_GAP : 0;
    $userName = generateRandomString();
    $params = [
        generateRandomString(),
        $userName . '@email.com',
        $isConfirmedEmail,
        $isValidEmail,
        $validTs,
        $isValidEmail ? 1 : rand(0, 1),
    ];

    $values[] = "'" . implode("', '", $params) . "'";
}

$stmt = $conn->prepare(sprintf($query, '(' . implode('),(', $values) . ')'));

if ($stmt->execute()) {
    echo 'New records inserted successfully!' . PHP_EOL;
} else {
    echo 'Error: ' . $stmt->error . PHP_EOL;
}

$conn->close();

function generateRandomString($length = 32) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1, $length);
}

function isTrueWithProbability($probabilityPercent)
{
    return (int) (rand(1, 100) <= $probabilityPercent);
}
