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

  // Диапазон дат для поиска заказа
  $dateTo = date("2013-12-31");  
  $dateFrom = date("2013-01-01");
  // $dateTo = date("Y-m-d");  
  // $dateFrom = date("Y-m-d");
  
  // Процент "похожести" для принятия решения о соответствии номеров
  // В "боевом" варианте используем порог 94% - приблизительно одна "грубая" ошибка в номере из 11 цифр
  // В тестовом примере ставим 75%, чтобы считать совпадением даже с одной ошибкой в номере заказа из 5 цифр
  $similarityPercentage = 75; 
  
  // Код города для дополнения городских номеров
  $phoneCode = '7495';
  
  // Показывать таблицу "похожести" для отладки?
  $showDebugInfo = true;

  if (isset($_REQUEST['trackingNumber'])) {
    $trackingNumber = normalizePhone($_REQUEST['trackingNumber']);
  
    // Запрос токена доступа
    $url = "https://$server:$port/api/0/auth/access_token";
    $params = array( 
      'user_id'     => $userId, 
      'user_secret' => $userSecret
    );
    $data = curlGet($url, $params);
    $accessToken = trim($data, '"');
    
    // Получение списка организаций
    $url = "https://$server:$port/api/0/organization/list";
    $params = array(
      'access_token' => $accessToken
    );
    $json = curlGet($url, $params);
    $orgList = json_decode($json, true);

    // Получение идентификатора первой в списке организации
    $orgGuid = $orgList[0]['id'];

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
    $deliveryOrders = json_decode($json, true)['deliveryOrders'];
          
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
  }
?>

<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Айко, где мой заказ?</title>
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">
    
    <style>
      .jumbotron { padding-top: 3rem; padding-bottom: 3rem; margin-bottom: 0; background-color: #fff; }
      @media (min-width: 768px) { .jumbotron { padding-top: 6rem; padding-bottom: 6rem; } }
      .jumbotron p:last-child { margin-bottom: 0; }
      .jumbotron-heading { font-weight: 300; }
      .jumbotron .container { max-width: 40rem; }
      footer { padding-top: 3rem; padding-bottom: 3rem; }
      footer p { margin-bottom: .25rem; }
    </style>
  </head>

  <body>
    <main role="main">
      <section class="jumbotron text-center">
        <div class="container">
          <h1 class="jumbotron-heading">Айко, где мой заказ?</h1>
          <p class="lead text-muted">Проверка статуса заказа по номеру телефона или номеру заказа</p>
        </div>
      </section>
      
      <?php if (isset($_REQUEST['trackingNumber'])): ?>
      <div class="search-result pb-5">
        <div class="container">
        <?php
          $totalOrdersShown = 0;

          // Если есть похожесть 100%, значит уверенно выводим заказ
          foreach ($orders as $key => $order) {
            $courier = null;
            if (isset($couriers[$order['courierInfo']['courierId']]))
              $courier = $couriers[$order['courierInfo']['courierId']];
            if ($order['similarity'] > $similarityPercentage) {
              if ($totalOrdersShown > 0) echo '<br>';
              $totalOrdersShown++;
              $output = showOrderInfo($order, $courier);
              echo $output;
            }
          }

          if ($totalOrdersShown == 0) {
            echo '<div class="card"><div class="card-body">' .
            '<p class="card-text">Мы проверили все заказы, оформленные за последнее время. ' .
            'К сожалению, <strong>не удалось найти ни одного заказа</strong> по указанному номеру.</p>' .
            '<p class="card-text">Если мы ошиблись&nbsp;&mdash; просим созвониться с оператором по телефону ' .
            '<nobr><a href="tel:+79997777777">+7 (999) 777-77-77</a></nobr>.' . 
            '<p class="card-text">Иначе предлагаем оформить новый заказ <a href="https://kvikfud.tech">здесь</a>!</p>' . 
            '</div></div>';
          }
        ?>
        </div>
      </div>
      <?php endif; ?>     

      <div class="py-5 bg-light">
        <div class="container">  
          <form class="needs-validation" novalidate role="form" method="get" action="">
            <div class="form-group">
              <label for="trackingNumber">Номер телефона или номер заказа</label>
              <input type="text" class="form-control" id="trackingNumber" name="trackingNumber" aria-describedby="trackingNumberHelp" placeholder="+79997777777" value="<?php if (isset($_REQUEST['trackingNumber'])) echo $trackingNumber; ?>" required>
              <small id="trackingNumberHelp" class="form-text text-muted">Формат номера произвольный, при проверке уберём всё лишнее</small>
            </div>
            <button type="submit" class="btn btn-primary">Проверить статус</button>
          </form>
        </div>
      </div>
    </main>      
    
    <footer class="text-muted">
      <div class="container">
        <p>Разработка сервиса&nbsp;&mdash; &quot;КвикФуд Технологии&quot;, 2019. <a href="https://github.com/fisher85/iiko-api/tree/master/iikoStatus">Исходный код на GitHub</a></p>
        <p>Хотите автоматизировать кафе или службу доставки? Пишите&nbsp;&mdash; <a href="mailto:hello@kvikfud.tech">hello@kvikfud.tech</a>. Запускаем Айко (iiko) для приема заказов, подключаем современную IP-телефонию, настраиваем интеграцию с сайтом, DeliveryClub, Яндекс.Едой <a href="https://kvikfud.tech">и не только</a>.</p>
      </div>
    </footer>
    
    <?php if ($showDebugInfo && isset($_REQUEST['trackingNumber'])): ?>
    <div class="album py-5 bg-light">
      <div class="container">  
        <p>Информация для отладки: список заказов</p>
        <table class="table table-sm table-striped table-bordered">
          <thead>
            <tr>
              <th>Номер заказа, кухня, статус
              <th>Время создания доставки, тип доставки, источник заказа
              <th>Оператор, курьер              
              <th>Телефон клиента, похожесть номера
            </tr>
          </thead>
          <tbody>
          <?php
            foreach ($orders as $key => $order) {
              echo '<tr><td>' . $order['number'] . ', ' .
                explode(' ', $order['deliveryTerminal']['restaurantName'])[0] . ', ' .
                '<nobr>[' . $order['status'] . ']</nobr>';
              
              echo '<td>' . $order['createdTime'] . ', ';
              if ($order['orderType']['name'] === 'Доставка курьером') echo 'курьером';
              if ($order['orderType']['name'] === 'Доставка самовывоз') echo 'cамовывоз';
              if (is_integer(strpos($order['comment'], 'Номер заказа DC:'))) echo ', DeliveryClub';
              
              echo '<td>' . $order['operator']['firstName'] . ' ' . $order['operator']['lastName'];
              $courierId = $order['courierInfo']['courierId']; 
              if (isset($couriers[$courierId])) 
                echo ', <i>' . $couriers[$courierId][0] . ' ' . $couriers[$courierId][1] . 
                ' [' . $couriers[$courierId][2] . ']</i>';
  
              echo '<td>' . $order['customer']['phone'] . ' <strong>[' . number_format($order['similarity'], 2) . '%]</strong>';
            }
          ?>            
          </tbody>
        </table>
      </div>
    </div>    
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>

    <script>
    (function() {
      'use strict';
      window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
          form.addEventListener('submit', function(event) {
            if (form.checkValidity() === false) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add('was-validated');
          }, false);
        });
      }, false);
    })();
    </script>  
  </body>
</html>    
      
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
    
    // echo 'curlGet: <a href="' . $options[CURLOPT_URL] . '">' . $options[CURLOPT_URL] . '</a><br>';
    
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
    if ($order['orderType']['name'] === 'Доставка самовывоз') $pickup = true;
          
    // Перевод статуса на понятный язык
    $badgeClass = "";
    $orderStatusText = "";
          
    if ($order['status'] === 'Новая' ||
        $order['status'] === 'Не подтверждена') {
          $badgeClass = "badge-info";
          $orderStatusText = "В обработке";
    }
            
    if ($order['status'] === 'Готовится') {
      $badgeClass = "badge-warning";
      $orderStatusText = "Готовится";
    }
          
    if ($order['status'] === 'Ждет отправки') {
      $badgeClass = "badge-warning";
      $orderStatusText = "Готов, ждет отправки";
    }        
          
    if ($order['status'] === 'В пути') {
      $badgeClass = "badge-warning";
      $orderStatusText = "В пути, передан курьеру";
    }
                
    if ($order['status'] === 'Готово') {
      $badgeClass = "badge-success";
      $orderStatusText = "Готов";
    }
          
    if ($order['status'] === 'Закрыта' ||
        $order['status'] === 'Доставлена') {
          $badgeClass = "badge-success";
          $orderStatusText = "Доставлен";
          if ($pickup) $orderStatusText = "Готов";
    }
          
    if ($order['status'] === 'Отменена') {
      $badgeClass = "badge-danger";
      $orderStatusText = "Отменен";
    }      

    $result = '<div class="card">';
    $result .= '<h4 class="card-header">Заказ ' . $order['number'] . 
      '&nbsp;&mdash; <span class="badge ' . $badgeClass . '">' . $orderStatusText . '</span></h4>';

    $result .= '<ul class="list-group list-group-flush">';        
        
    // Адрес можно показать с номером дома, если еще есть квартиры в доме
    $address = "не опеределен";
    if (strlen($order['address']['street']) > 0) {
      $address = $order['address']['street'];
      if (strlen($order['address']['home']) > 0 &&
         (strlen($order['address']['housing']) > 0 ||
          strlen($order['address']['apartment']) > 0 ||
          strlen($order['address']['entrance']) > 0 ||
          strlen($order['address']['floor']) > 0)) 
            $address .= ', ' . $order['address']['home'];
    }
        
    $result .= '<li class="list-group-item">';
        
    // Замена первых цифр номера клиента на ***
    $hiddenPhone = str_pad(
      substr($order['customer']['phone'], -4), 
      strlen($order['customer']['phone']), 
      "*", 
      STR_PAD_LEFT
    );
    $result .= 'Телефон Клиента: ' . $hiddenPhone . '. ';

    if (is_integer(strpos($order['comment'], 'Номер заказа DC:'))) 
      $result .= 'Заказ из DeliveryClub. ';
        
    if ($pickup) {
      if (isset($order['deliveryTerminal']['address'])) 
        $result .= 'Самовывоз, адрес производства: ' . $order['deliveryTerminal']['address'] . '. ';
      else
        $result .= 'Самовывоз. ';
    } else
      $result .= 'Курьерская доставка по адресу: ' . $address . '. ';

    if (isset($order['createdTime']))
      $result .= '<li class="list-group-item">Принят: ' . showTime($order['createdTime']) . 
                 '. Оператор&nbsp;&mdash; ' . $order['operator']['firstName'] . ' ' . $order['operator']['lastName'] . 
                 ', телефон: <a href="tel:+79997777777">+79997777777</a></li>';
    
    if (isset($order['printTime'])) // Время сервисной печати проставляется реальное, но позже, в момент печати накладной
      $result .= '<li class="list-group-item">Передан на производство: ' . showTime($order['printTime']) . '</li>';

    if (isset($order['sendTime']) && isset($courier))
      $result .= '<li class="list-group-item">Отправлен с курьером: ' . showTime($order['sendTime']) . 
                 '. Курьер&nbsp;&mdash; ' . $courier[0] . ' ' . $courier[1] . ', телефон: <a href="tel:' . $courierPhone . 
                 '">' . $courierPhone . '</a></li>';

    if (isset($order['deliveryDate'])) {
      if ($pickup)
        $result .= '<li class="list-group-item">Расчетное время готовности: ' . showTime($order['deliveryDate']) . '</li>';
      else
        $result .= '<li class="list-group-item">Расчетное время доставки: ' . showTime($order['deliveryDate']) . '</li>';
    }
        
    if (isset($order['actualTime']) && ! $pickup)
      $result .= '<li class="list-group-item">Фактическое время доставки: ' . showTime($order['actualTime']) . '</li>';
      
    if (isset($order['billTime']) && $pickup)
      $result .= '<li class="list-group-item">Приготовлен, ожидает самовывоза: ' . showTime($order['billTime']) . '</li>';
        
    $result .= '</ul>';
    $result .= '</div>';
    return $result;
  }
?>