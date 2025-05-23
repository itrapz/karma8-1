<?php
require_once('connect_services.php');

const WORKERS = 150;
const SELECT_UPPER_LIMIT = 2000;
const SEND_DAY_LIMIT_FIRST = 3;
const SEND_DAY_LIMIT_LAST = 1;
const TIME_AMOUNT= 60 * 60 * 24; //1 day in seconds, look data for the 1 day after th point

$firstRangeMin = TIME_AMOUNT * SEND_DAY_LIMIT_LAST - TIME_AMOUNT;
$lastRangeMin = TIME_AMOUNT * SEND_DAY_LIMIT_LAST;
$firstRangeMax = TIME_AMOUNT * SEND_DAY_LIMIT_FIRST - TIME_AMOUNT;
$lastRangeMax = TIME_AMOUNT * SEND_DAY_LIMIT_FIRST;

$count = get_cnt_ready_mailings($conn, $firstRangeMin, $lastRangeMin, $firstRangeMax, $lastRangeMax);
$threadItemsLimit = min((int) floor($count / WORKERS) + 1, SELECT_UPPER_LIMIT);
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
            echo 'Chunk #' . $threadNumber . ' error: ' . $exception->getMessage() . PHP_EOL;
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $queue = DEFAULT_QUEUE;

                $redis->lPush($queue, json_encode($row));
            }
        }
        echo 'Chunk #' . $threadNumber . ' all jobs have been sent.' . PHP_EOL;
    };

    $offset += $threadItemsLimit;
}

init_jobs($callbacks);

$jobsCount =  $redis->lLen(DEFAULT_QUEUE);

run_workers();

$conn->close();

if (!isset($argv[1]) || $argv[1] !== 'nowait') {
    $repeat = true;
    $percent = 0;
    $redis->get('handled_tasks');
    echo "Jobs handled: {$percent}% ({$redis->get('handled_tasks')} / {$jobsCount})";

    while ($repeat) {
        $handledTasks = $redis->get('handled_tasks');
        $percent = floor(($handledTasks * 100) / $jobsCount);
        echo "\rJobs handled: {$percent}% ($handledTasks / {$jobsCount})";
        if ($handledTasks >= $jobsCount) {
            $redis->set('handled_tasks', 0);
            $repeat = false;
            echo PHP_EOL . 'Job handling finished, spent money: ' . (int) $redis->get('spent_money') . '₽' . PHP_EOL;
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

function init_jobs(array $callbacks) {
    foreach ($callbacks as $call) {
        $call();
    }
}

function run_workers() {
    foreach (range(1, WORKERS) as $threadNumber) {
        exec(sprintf('php worker.php > log/%s-worker-%s.log 2>&1 &', date('Y-m-d', time()), $threadNumber));
    }
}
