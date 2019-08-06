<?php
  /*
   * Монитор загрузки производств
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

  // $date = "2019-06-07";
  $date = date("Y-m-d");

  // Имена производств для подсчета статистики,
  // сопоставление с атрибутом $order['deliveryTerminal']['restaurantName']
  $restaurantNames = array('Терминал доставки по умолчанию (null)', 'Пушкина', 'Достоевского', 'Всего');

  // Отслеживаемые статусы заказов
  $statuses = array('Новая', 'Готовится', 'Ждет отправки', 'Приготовлен', 'Всего');
?>

<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- <meta http-equiv="refresh" content="60; URL=index.php"> -->

    <title>iikoBoard &ndash; Монитор загрузки производств</title>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs-3.3.7/jq-3.3.1/dt-1.10.18/datatables.min.css"/>
    <script type="text/javascript" src="https://cdn.datatables.net/v/bs-3.3.7/jq-3.3.1/dt-1.10.18/datatables.min.js"></script>
    
    <style> 
      td { 
        border: 1px solid #ccc; 
      } 
      
      div.dataTables_filter {
        text-align: left !important;
      }
      
      div.dataTables_info {
        text-align: right !important;
      }      
      
      .container-full {
        margin: 0 auto;
        width: 95%;
      }
    </style>
  </head>

  <body class="bg-light">
    <div class="container-full">
      <div class="row">
        <h1>iikoBoard &ndash; Монитор загрузки производств</h1>
        
        <p class="lead">
        <?php 
          echo '<a href="https://docs.google.com/document/d/1pRQNIn46GH1LVqzBUY5TdIIUuSCOl-A_xeCBbogd2bE/">Документация iikoBizApi</a><br>';
          
          $now = new DateTime('now'); 
          echo 'Время обновления ' . $now->format('d-m-Y H:i:s') . '<br>';
          
          echo 'Логин API iikoDelivery: ' . $userId . '<br>'; 
          
          // Запрос токена доступа
          $url = "https://$server:$port/api/0/auth/access_token";
          $params = array( 
            'user_id'     => $userId, 
            'user_secret' => $userSecret
          );
          $data = curlGet($url, $params);
          $accessToken = trim($data, '"');
          echo 'Токен доступа: ' . $accessToken . '<br>';
          
          // Получение списка организаций
          $url = "https://$server:$port/api/0/organization/list";
          $params = array(
            'access_token' => $accessToken
          );
          $json = curlGet($url, $params);
          $orgList = json_decode($json, true);

          // Получение идентификатора первой в списке организации
          $orgGuid = $orgList[0]['id'];

          // Разбор списка организаций
          echo 'Подключение к организации: ' . $orgList[0]['name'] . ', Guid ' . $orgGuid . 
            ' (всего доступных организаций &ndash; ' . count($orgList) . ')<br>';
          
          // Получение списка доставок указанной организации в указанный день
          $url = "https://$server:$port/api/0/orders/deliveryOrders";
          $params = array(
            'access_token' => $accessToken, 
            'organization' => $orgGuid,
            'dateFrom' => $date,
            'dateTo' => $date,
            'request_timeout' => '00:02:00'
          );
          $json = curlGet($url, $params);
          $deliveryOrders = json_decode($json, true)['deliveryOrders'];
          echo 'Всего заказов за день ' . $date . ': ' . count($deliveryOrders) . '<br>';
          echo 'Если заказов 0, можно интерактивно создать <a href="https://examples.iiko.ru/orders/?example=base_create_order">здесь</a><br>';
        ?>
        </p>
        
        <table id="table-board" class="table table-sm table-hover table-striped table-bordered" style="width:100%">
          <thead>
            <tr>
              <th>Отслеживаемый статус заказа
              <?php 
                foreach ($restaurantNames as $restaurantName) 
                  echo '<th>' . $restaurantName; 
                foreach ($restaurantNames as $restaurantName) 
                  if ($restaurantName !== 'Всего') echo '<th>' . $restaurantName . ' &ndash; номера заказов'; 
              ?>
            </tr>
          </thead>
          <tbody>
          <?php
            // Подготовка массивов для сбора статистики
            $statistics = array();
            $numbers = array();

            foreach ($statuses as $status) 
              foreach ($restaurantNames as $restaurantName) {
                $statistics[$status][$restaurantName] = 0;
                $numbers[$status][$restaurantName] = '';
            }

            // Просмотр всех заказов и сбор статистики
            foreach ($deliveryOrders as $order) {
              if (!in_array($order['status'], $statuses)) continue;

              $restaurantName = explode(' ', $order['deliveryTerminal']['restaurantName'])[0];
              if ($order['deliveryTerminal'] == null)
                $restaurantName = $restaurantNames[0];
                  
              // Общая статистика собирается для проверки совпадения с суммой по производствам
              $statistics[$order['status']][$restaurantName]++; // Ячейка
              $statistics[$order['status']]['Всего']++; // Итог в строке
              $statistics['Всего'][$restaurantName]++; // Итог в столбце
              $statistics['Всего']['Всего']++; // Общий итог
              
              $numbers[$order['status']][$restaurantName] .= $order['number'] . " ";
            }

            // Вывод данных
            foreach ($statuses as $status) {
              echo '<tr><td>' . $status;
              foreach ($restaurantNames as $restaurantName) 
                echo '<td>' . $statistics[$status][$restaurantName];
              foreach ($restaurantNames as $restaurantName)
                if ($restaurantName !== 'Всего')
                  echo '<td>' . $numbers[$status][$restaurantName];
            }

            // dweeti.io & freeboard.io
            /*
            $url = "https://dweet.io/dweet/for/iikoboard-uniquekey";
            $params = array(
                'lenina'   => $statistics['Всего'][0], 
                'pushkina' => $statistics['Всего'][1],
            );
            $json = curlGet($url, $params);
            */
          ?>
          </tbody>
          <tfoot>
            <tr>
              <th>Отслеживаемый статус заказа
              <?php
                foreach ($restaurantNames as $restaurantName) 
                  echo '<th>' . $restaurantName; 
                foreach ($restaurantNames as $restaurantName) 
                  if ($restaurantName !== 'Всего') echo '<th>' . $restaurantName . ' &ndash; номера заказов'; 
              ?>
            </tr>
          </tfoot>          
        </table>
      </div>
    </div>
    
  <!-- page script -->
  <script>
    $(document).ready(function() {
      $('#table-board').DataTable( {
        'dom': "<'row'<'col-sm-6'f><'col-sm-6'i>><'row'<'col-sm-12'tr>><'row'<'col-sm-12'i>>",
        'language': { 'url': '//cdn.datatables.net/plug-ins/1.10.19/i18n/Russian.json' },
        'order': []
      } );
    } );
  </script>
    
  </body>
</html>

<?php
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
?>