<?php
require_once('connect_services.php');

const FROM_EMAIL = 'info@website.com';
const EXPIRING_TEXT_TEMPLATE = '%s, your subscription is expiring soon';
const WAITING_CYCLE_TIMEOUT = 5;
const WAITING_CYCLES = 3;

echo  date('d.m.Y H:i:s', time()) . ' | Users mailing worker started...' . PHP_EOL;

$waitingCycles = 1;
$needToWait = true;
$numOfProcessedTasks = 0;

while ($needToWait) {
    $job = $redis->lPop(DEFAULT_QUEUE);
    if ($job) {
        $params = json_decode($job, true);
        echo date('d.m.Y H:i:s', time()) . ' | #id: ' . $params['id'] . ' Processing ' . PHP_EOL;
        process_email($params, $conn);
        $redis->incr('handled_tasks');
        echo date('d.m.Y H:i:s', time()) . ' | #id: ' . $params['id'] . ' Sent' . PHP_EOL;
    } else {
        $waitingCycles++;
        echo 'No tasks. Waiting...'. PHP_EOL;
        sleep(WAITING_CYCLE_TIMEOUT);
        if ($waitingCycles > WAITING_CYCLES) {
            $needToWait = false;
            echo date('d.m.Y H:i:s', time()) . ' | No tasks to handle. Shutting down.'. PHP_EOL;
        }
    }
}

function process_email(array $params, mysqli $conn) {
    if ($params['checked'] === 0) {

        $valid = check_email($params['email']);
        set_check_user($valid, $params['id'], $conn);
    } else {
        $valid = $params['valid'];
    }
    if (!$valid) {
        return;
    }

    send_email(FROM_EMAIL, $params['email'], sprintf(EXPIRING_TEXT_TEMPLATE, $params['username']));
}

function set_check_user(int $valid, int $userId, mysqli $conn) {
    $query = 'UPDATE users SET valid = ?, checked = 1 WHERE id = ?';
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $valid, $id);

    if ($stmt->execute()) {
        echo sprintf('%s | User #%d email validation check updated successfully!', date('d.m.Y H:i:s', time()), $userId) . PHP_EOL;
    } else {
        echo sprintf('%s | User #%d ERROR on validation check updating: ', date('d.m.Y H:i:s', time()), $userId) . $stmt->error  . PHP_EOL;
    }
}

function check_email(string $email): bool {
    sleep(rand(1, 60));

    echo sprintf('%s | Email %s was checked', date('d.m.Y H:i:s', time()), $email) . PHP_EOL;

    return rand(0, 1) === 1;
}

function send_email($from, $to, $text): void {
    sleep(rand(1, 10));

    echo sprintf('%s | Email from: %s to: %s text: %s | Was sent successfully!', date('d.m.Y H:i:s', time()), $from, $to, $text) . PHP_EOL;
}
