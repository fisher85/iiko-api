<?php

/*
 * Проверка статуса заказа Айко по номеру телефона клиента или по номеру заказа
 *
 * Пример разработан в "КвикФуд Технологиях": https://kvikfud.tech/
 *
 * Больше подробностей в документации:
 * iikoBiz API: https://docs.google.com/document/d/1pRQNIn46GH1LVqzBUY5TdIIUuSCOl-A_xeCBbogd2bE/
 * iikoCard API: https://docs.google.com/document/d/1kuhs94UV_0oUkI2CI3uOsNo_dydmh9Q0MFoDWmhzwxc/
 * https://examples.iiko.ru/
 */

// Тестовый аккаунт Айко
$server = "iiko.biz";
$port = "9900";  
$userId = "demoDelivery"; 
$userSecret = "PI1yFaKFCGvvJKi";

// Отслеживаемый номер (номер заказа или номер телефона)
$trackingNumber = 12345; 

// Диапазон дат для поиска заказа
$dateTo = date("2013-12-31");  
$dateFrom = date("2013-01-01");

// Процент "похожести" для принятия решения о соответствии номеров
// В "боевом" варианте используем порог 94% - приблизительно одна "грубая" ошибка в номере из 11 цифр
$similarityPercentage = 65; 
// Код города для дополнения городских номеров
$phoneCode = '7495';
  
// Запрос токена доступа
$url = "https://$server:$port/api/0/auth/access_token";
$params = array( 
    'user_id'     => $userId, 
    'user_secret' => $userSecret
);
$data = curlGet($url, $params);
$accessToken = trim($data, '"');
echo "Токен доступа: $accessToken <br><br>";

// Получение списка организаций
$url = "https://$server:$port/api/0/organization/list";
$params = array(
    'access_token' => $accessToken
);
$json = curlGet($url, $params);
$orgList = json_decode($json, true);
print_r($orgList);

// Получение идентификатора первой в списке организации
$orgGuid = $orgList[0]['id'];

// Разбор списка организаций
echo "<br><br>";
echo "Всего организаций: " . count($orgList) . "<br>";
echo "Название первой организации: " . $orgList[0]['name'] . "<br>";
echo "GUID первой организации: " . $orgGuid . "<br><br>";

// Получение списка доставок указанной организации за период времени
$url = "https://$server:$port/api/0/orders/deliveryOrders";
$params = array(
    'access_token'    => $accessToken, 
    'organization'    => $orgGuid,
    'dateFrom'        => $dateFrom,
    'dateTo'          => $dateTo,
    'request_timeout' => "00:02:00"
);
$json = curlGet($url, $params);
echo substr($json, 0, 1000);
$deliveryOrders = json_decode($json, true)['deliveryOrders'];
echo "<br><br>Всего заказов: " . count($deliveryOrders) . "<br>";

// Новый массив с нормализованными номерами телефонов и значениями похожести
$orders = array();
    
// Основной цикл: для каждого заказа из списка доставок
// опеределяется похожесть отслеживаемого номера и номера заказа, номера телефона клиента
foreach ($deliveryOrders as $order) {
    // Массив значений похожести для нескольких пар номеров
    $percents = array(); 
    
    // $percents[0] - похожесть отслеживаемого номера и номера телефона клиента
    $order['customer']['phone'] = normalizePhone($order['customer']['phone']);
    similar_text($trackingNumber, $order['customer']['phone'], $percents[]);
    
    // $percents[1] - похожесть отслеживаемого номера и городского номера телефона клиента
    if (strlen($trackingNumber) < 10) 
        similar_text($phoneCode . $trackingNumber, $order['customer']['phone'], $percents[]);
  
    // В связке Айко + Манго телефонные номера в базе сохраняются с префиксом +7
    // $percents[2] - похожесть отслеживаемого номера и номера телефона клиента с 8
    if (substr($trackingNumber, 0, 1) === "8")
        similar_text(
            "7" . substr($trackingNumber, 1, strlen($trackingNumber) - 1), 
            $order['customer']['phone'], 
            $percents[]
        );
  
    // $percents[3] - похожесть отслеживаемого номера и номера заказа
    similar_text($trackingNumber, $order['number'], $percents[]);          
  
    // Решение о соответствии отслеживаемого номера одному из базы принимаем по максимальной похожести
    $order['similarity'] = max($percents);
    $orders[] = $order;
}
    
// Сортируем массив заказов по убыванию значения похожести
usort($orders, function($a, $b) {
    return $b['similarity'] - $a['similarity'];
});
    
// Получение списка курьеров для вывода подробной информации о заказах
$url = "https://$server:$port/api/0/rmsSettings/getCouriers";
$params = array(
    'access_token' => $accessToken, 
    'organization' => $orgGuid,
);    
$json = curlGet($url, $params);
$jsonDecoded = json_decode($json, true);
$couriers = array();
foreach ($jsonDecoded['users'] as $courier) {
    $couriers[$courier['id']] = array(
        $courier['firstName'], 
        $courier['lastName'], 
        $courier['cellPhone']
    );
}
      
// Вывод результатов поиска
echo "<br><strong>Результаты поиска (похожесть для отслеживаемого номера [" .
     $trackingNumber . "] > " . $similarityPercentage . "%): </strong><br><br>";

$totalOrdersShown = 0;

foreach ($orders as $key => $order) {
    $courier = null;
    if (isset($couriers[$order['courierInfo']['courierId']]))
        $courier = $couriers[$order['courierInfo']['courierId']];
    if ($order['similarity'] > $similarityPercentage) {
        $totalOrdersShown++;
        showOrderInfo($order, $courier);
    }
}

if ($totalOrdersShown == 0) 
    echo "К сожалению, не удалось найти ни одного заказа по указанному номеру.";

// Вывод заказов для отладки и экспериментов
?>

<style>
  td { border: 1px solid #ccc; }
</style>
<table>
  <tr>
    <th>Похожесть (similarity)
    <th>Номер заказа
    <th>Статус
    <th>Тип доставки, источник заказа
    <th>Время создания доставки (createdTime)
    <th>Время сервисной печати (printTime)
    <th>Время печати накладной (время пречека, billTime)
    <th>Время отправки доставки (sendTime)
    <th>Расчетное время, к которому нужно доставить заказ (deliveryDate)
    <th>Фактическое время доставки (actualTime)
    <th>Время закрытия доставки (closeTime)
    <th>Оператор, курьер              
    <th>Клиент, телефон, адрес доставки
    <th>Сумма заказа, скидка
    <th>Тип оплаты
  </tr>
  
<?php

foreach ($orders as $key => $order) {
    echo "<tr><td><strong>" . number_format($order['similarity'], 2) . "%</strong>";
    echo "<td>" . $order['number'];
    echo "<td>" . $order['status'];

    echo "<td>";
    if ($order['orderType']['name'] === "Доставка курьером") echo "Курьером";
    if ($order['orderType']['name'] === "Доставка самовывоз") echo "Самовывоз";
    if (is_integer(strpos($order['comment'], "Номер заказа DC:"))) 
        echo " (DeliveryClub)";    
    
    echo "<td>" . $order['createdTime'];
    echo "<td>" . $order['printTime'];
    echo "<td>" . $order['billTime'];
    echo "<td>" . $order['sendTime'];
    echo "<td>" . $order['deliveryDate'];
    echo "<td>" . $order['actualTime'];
    echo "<td>" . $order['closeTime'];
    
    echo "<td>Оператор: " . $order['operator']['firstName'] . " " . $order['operator']['lastName'];
    $courierId = $order['courierInfo']['courierId']; 
    if (isset($couriers[$courierId])) 
        echo "<br>Курьер: " . $couriers[$courierId][0] . " " . $couriers[$courierId][1];

    echo "<td>" . $order['customer']['name'] . ", " . $order['customer']['phone'];
    if (strlen($order['address']['street']) > 0) {
        echo "<br>(" . $order['address']['street'];
        if (strlen($order['address']['home']) > 0) echo ", " . $order['address']['home'] . ")";
            else echo ")";
    }
    
    echo "<td>" . $order['sum'] . "&nbsp;&#8381; / " . $order['discount'] . "&nbsp;&#8381;";
    echo "<td>";
    if (isset($order['payments'][0]['paymentType']['name'])) 
        echo $order['payments'][0]['paymentType']['name'];
}
?>            

</table>

<?php

// Нормализация номера телефона - удаление всех символов, кроме цифр
function normalizePhone($phoneNumber) 
{
    return preg_replace("/[^0-9]/", "", $phoneNumber);
}

function showTime($time) 
{
    return "<strong>" . date("H:i", strtotime($time)) . "</strong>";
}

function curlGet($url, array $get = null) 
{
    $options = array(
        CURLOPT_URL => $url . (strpos($url, "?") === false ? "?" : "") . http_build_query($get),
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
    );
    
    echo 'curlGet: <a href="' . $options[CURLOPT_URL] . '">' . $options[CURLOPT_URL] . '</a><br>';
    
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function showOrderInfo($order, $courier) 
{
    $result = "";
    
    $courierPhone = null;
    if (isset($courier)) $courierPhone = $courier[2];
    
    $pickup = false;
    if ($order['orderType']['name'] === "Доставка самовывоз") 
        $pickup = true;
      
    // Перевод статуса на понятный язык
    $orderStatusText = "";

    if ($order['status'] === 'Новая' ||
        $order['status'] === 'Не подтверждена' ||
        $order['status'] === 'Ждет отправки') $orderStatusText = "В обработке";
      
    if ($order['status'] === 'Готовится') $orderStatusText = "Готовится";
    
    if ($order['status'] === 'В пути') $orderStatusText = "В пути, передан курьеру";
          
    if ($order['status'] === 'Готово') $orderStatusText = "Готов";
    
    if ($order['status'] === 'Закрыта' ||
        $order['status'] === 'Доставлена') $orderStatusText = "Доставлен";
    
    if ($order['status'] === 'Отменена') $orderStatusText = "Отменен";

    $result = "Заказ [" . $order['number'] . "] &ndash; " . $orderStatusText . "<br>";
    
    // Адрес можно показать с номером дома, если еще есть квартиры в доме
    $address = "не опеределен";
    if (strlen($order['address']['street']) > 0) {
        $address = $order['address']['street'];
        if (strlen($order['address']['home']) > 0 &&
           (strlen($order['address']['housing']) > 0 ||
            strlen($order['address']['apartment']) > 0 ||
            strlen($order['address']['entrance']) > 0 ||
            strlen($order['address']['floor']) > 0)) $address .= ', ' . $order['address']['home'];
    }
    
    // Замена первых цифр номера клиента на ***
    $hiddenPhone = str_pad(
        substr($order['customer']['phone'], -4), 
        strlen($order['customer']['phone']), 
        "*", 
        STR_PAD_LEFT
    );
    $result .= "Телефон Клиента: " . $hiddenPhone . "<br>";
    
    if (is_integer(strpos($order['comment'], "Номер заказа DC:"))) 
        $result .= "Заказ из DeliveryClub";
      
    if ($pickup)
        $result .= "Самовывоз, адрес производства: " . $order['deliveryTerminal']['address'] . "<br>";
    else
        $result .= "Курьерская доставка по адресу: " . $address . "<br>";
    
    if (isset($order['createdTime']))
        $result .= "Принят: " . showTime($order['createdTime']) . ". " .
                   "Оператор &ndash; " . $order['operator']['firstName'] . " " .
                   $order['operator']['lastName'] . ", " .
                   "телефон: " . $order['operator']['cellPhone'] . "<br>";
                   
    if (isset($order['printTime']))
        $result .= "Передан на производство: " . showTime($order['printTime']) . "<br>";
      
    if (isset($order['sendTime']) && isset($courier))
        $result .= "Отправлен с курьером: " . showTime($order['sendTime']) . ". " .
                   "Курьер &ndash; " . $courier[0] . ' ' . $courier[1] . ", " . 
                   "телефон: " . $courierPhone . "<br>";

    if (isset($order['deliveryDate'])) {
        if ($pickup)
            $result .= "Расчетное время готовности: " . showTime($order['deliveryDate']) . "<br>";
        else
            $result .= "Расчетное время доставки: " . showTime($order['deliveryDate']) . "<br>";
    }
    
    if (isset($order['actualTime'])) {
        if ($pickup)
            // Альтернативный вариант - показывать время billTime, а логистам (сборщикам)
            // для самовывоза считать "флагом" готовности момент печати чека (накладной) клиенту
            $result .= "Приготовлен: " . showTime($order['deliveryDate']) . "<br>";
        else
            $result .= "Фактическое время доставки: " . showTime($order['actualTime']) . "<br>";
    }      
  
    echo $result . "<br>";
}
