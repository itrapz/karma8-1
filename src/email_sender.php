<?php
require_once('connect_services.php');

const WORKERS = 50;
const SEND_DAY_LIMIT_FIRST = 3;
const SEND_DAY_LIMIT_LAST = 1;
const TIME_AMOUNT= 60 * 60 * 24; //1 day in seconds, look data for the 1 day after th point


$firstRangeMin = TIME_AMOUNT * SEND_DAY_LIMIT_LAST - TIME_AMOUNT;
$lastRangeMin = TIME_AMOUNT * SEND_DAY_LIMIT_LAST;
$firstRangeMax = TIME_AMOUNT * SEND_DAY_LIMIT_FIRST - TIME_AMOUNT;
$lastRangeMax = TIME_AMOUNT * SEND_DAY_LIMIT_FIRST;

$count = get_cnt_ready_mailings($conn, $firstRangeMin, $lastRangeMin, $firstRangeMax, $lastRangeMax);
$threadItemsLimit = (int) floor($count / WORKERS);

$query = '
    SELECT * 
    FROM test.users
    WHERE 
        validts > UNIX_TIMESTAMP()
    AND (
        validts BETWEEN UNIX_TIMESTAMP() + ? AND UNIX_TIMESTAMP() + ?
        OR
        validts BETWEEN UNIX_TIMESTAMP() + ? AND UNIX_TIMESTAMP() + ?
    )
    LIMIT ? OFFSET ?
';

$offset = 0;
$callbacks = [];

foreach (range(1, WORKERS) as $threadNumber) {
    $callbacks[] = function() use ($conn, $query, $redis, $firstRangeMin, $lastRangeMin, $firstRangeMax, $lastRangeMax, $threadItemsLimit, $offset, $threadNumber) {

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiiii", $firstRangeMin, $lastRangeMin, $firstRangeMax, $lastRangeMax, $threadItemsLimit, $offset);

        try {
            $stmt->execute();
        } catch (\Exception $exception) {
            echo 'THREAD #' . $threadNumber . ' error: ' . $exception->getMessage() . PHP_EOL;
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $queue = DEFAULT_QUEUE;

                $redis->lPush($queue, json_encode($row));
            }
        }

        exec(sprintf('php worker.php > log/%s-worker-%s.log 2>&1 &', date('Y-m-d', time()), $threadNumber));

        echo 'THREAD #' . $threadNumber . ' all jobs have been sent.' . PHP_EOL;
    };

    $offset += $threadItemsLimit;
}

init_threads($callbacks);

$conn->close();
if ($argv[1] === 'wait') {
    $repeat = true;

    $percent = 0;
    $progress = 0;

    $redis->get('handled_tasks');

    echo "Tasks handled: {$percent}% ({$redis->get('handled_tasks')} / {$count})";

    while ($repeat) {
        $handledTasks = $redis->get('handled_tasks');
        $percent = floor(($handledTasks * 100) / $count);
        echo "\rTasks handled: {$percent}% ($handledTasks / {$count})";
        if ($handledTasks >=  $count) {
            $redis->set('handled_tasks', 0);
            $repeat = false;
            echo PHP_EOL;
        }
    }
}

function get_cnt_ready_mailings(mysqli $conn, int $firstRangeMin, int $lastRangeMin, int $firstRangeMax, int $lastRangeMax) {
    $query = '
    SELECT COUNT(id) FROM test.users
    WHERE 
        valid = 1
    AND
        validts > UNIX_TIMESTAMP()
    AND (
        validts BETWEEN UNIX_TIMESTAMP() + ? AND UNIX_TIMESTAMP() + ?
        OR
        validts BETWEEN UNIX_TIMESTAMP() + ? AND UNIX_TIMESTAMP() + ?
    )
';
    $count = 0;
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiii', $firstRangeMin, $lastRangeMin, $firstRangeMax, $lastRangeMax);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();

    return $count;
}

function init_threads(array $callbacks) {
    foreach ($callbacks as $call) {
        $call();
    }
}
