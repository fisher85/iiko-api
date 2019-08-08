<?php
$stateHolidays = array(
    '2018-01-01', '2018-01-02', '2018-01-03', '2018-01-04', '2018-01-05', '2018-01-06', '2018-01-08', 
    '2018-01-07',
    '2018-02-23', 
    '2018-03-08', 
    '2018-05-01',     
    '2018-05-09', 
    '2018-06-12', 
    '2018-11-04',  
    
    '2019-01-01', '2019-01-02', '2019-01-03', '2019-01-04', '2019-01-05', '2019-01-06', '2019-01-08', 
    '2019-01-07',
    '2019-02-23', 
    '2019-03-08', 
    '2019-05-01',     
    '2019-05-09', 
    '2019-06-12', 
    '2019-11-04',    
);

$stateWeekends = array(
    '2018-02-24', '2018-02-25', 
    '2018-03-09', '2018-03-10', '2018-03-11', 
    '2018-04-29', '2018-04-30',  
    '2018-05-02',         
    '2018-06-10', '2018-06-11', 
    '2018-11-03', '2018-11-05',
    
    '2019-02-24', 
    '2019-03-09', '2019-03-10',
    '2019-05-02', '2019-05-03', '2019-05-04', '2019-05-05',
    '2019-05-10', '2019-05-11', '2019-05-12', 
    '2018-11-02', '2018-11-03'
);

// Generate train data

// This folder keeps files YYYY-MM-DD.txt with data from
// https://$server:$port/api/0/orders/deliveryOrders

$cacheFolder = "cache/..";
$data = array();
$lastDate = date("Y-m-d");

$dir = new DirectoryIterator(dirname($cacheFolder));
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $pathname = $fileinfo->getPathname();
        $json = file_get_contents($pathname);
        
        // Daily sales analysis
        $orders = json_decode($json, true);
        $orderCount = 0;
        $orderSum = 0;
        foreach ($orders['deliveryOrders'] as $order) {
            if ($order['statusCode'] === 'CANCELLED') continue;
            $orderCount++;
            $orderSum += $order['sum'];
        }    
        
        $date = $fileinfo->getBasename('.txt');
        $lastDate = $date;
        $isStateHoliday = in_array($date, $stateHolidays);
        $isStateWeekend = in_array($date, $stateWeekends);
        
        $data[] = [
            'Date' => $date, 
            'Year' => date('Y', strtotime($date)),
            'Month' => date('n', strtotime($date)),
            'Week' => date('W', strtotime($date)),
            'Day' => date('j', strtotime($date)), // 1 - 31
            'DayOfWeek' => date('N', strtotime($date)), // 1 - Monday, 7 - Sunday
            'DayOfYear' => date('z', strtotime($date)), // 0 - Jan 01
            'StateHoliday' => $isStateHoliday ? 1 : 0,
            'StateWeekend' => $isStateWeekend ? 1 : 0,
            'Weekend' => date('N', strtotime($date)) > 4 ? 1 : 0,
            'PromoYandex' => 0,
            'PromoClub' => 0,
            'OrderCount' => $orderCount, 
            'OrderSum' => $orderSum
        ];
    }
}

$exists = 0;
if (file_exists('sales_train.csv')) {
    $f = fopen('sales_train.csv', 'w');
    $exists = 1;
}
else
    $f = fopen('sales_train.csv', 'a');
  
$firstLineKeys = false;
foreach ($data as $line) {
    if (empty($firstLineKeys)) {
        $firstLineKeys = array_keys($line);
        if (!$exists) fputcsv($f, $firstLineKeys);
        $firstLineKeys = array_flip($firstLineKeys);
    }
	
    // Using array_merge is important to maintain the order of keys acording to the first element
    fputcsv($f, array_merge($firstLineKeys, $line));
}

// Generate test data
$date = $lastDate;
$date = "2018-10-21";
$finishDate = "2020-01-01";
$data = array();

while (strtotime($date) < strtotime($finishDate)) {
    $date = date("Y-m-d", strtotime("+1 day", strtotime($date)));
    
    $isStateHoliday = in_array($date, $stateHolidays);
    $isStateWeekend = in_array($date, $stateWeekends);
    
    $data[] = [
        'Date' => $date, 
        'Year' => date('Y', strtotime($date)),
        'Month' => date('n', strtotime($date)),
        'Week' => date('W', strtotime($date)),
        'Day' => date('j', strtotime($date)), // 1 - 31
        'DayOfWeek' => date('N', strtotime($date)), // 1 - Monday, 7 - Sunday
        'DayOfYear' => date('z', strtotime($date)), // 0 - Jan 01
        'StateHoliday' => $isStateHoliday ? 1 : 0,
        'StateWeekend' => $isStateWeekend ? 1 : 0,
        'Weekend' => date('N', strtotime($date)) > 4 ? 1 : 0,
        'PromoYandex' => 0,
        'PromoClub' => 0,
        // 'OrderCount' => $orderCount, 
        // 'OrderSum' => $orderSum
    ];
}

$f = fopen('sales_test.csv', 'w');
$firstLineKeys = false;
foreach ($data as $line) {
    if (empty($firstLineKeys)) {
        $firstLineKeys = array_keys($line);
        fputcsv($f, $firstLineKeys);
        $firstLineKeys = array_flip($firstLineKeys);
    }
	
    fputcsv($f, array_merge($firstLineKeys, $line));
}
