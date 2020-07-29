<?php
  /*
   * Выгрузка меню ресторана
   *
   * Пример разработан Семёном Фудовым: https://fudov.ru/
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
?>

<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>iikoMenu &ndash; Выгрузка меню</title>

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
        <h1>iikoMenu &ndash; Выгрузка меню</h1>
        
        <p class="lead">
        <?php 
          echo '<a href="https://docs.google.com/document/d/1pRQNIn46GH1LVqzBUY5TdIIUuSCOl-A_xeCBbogd2bE/">Документация iikoBizApi</a><br>';
          
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
          
          // Получение и разбор меню
          $url = "https://$server:$port/api/0/nomenclature/$orgGuid";
          $params = array(
            'access_token' => $accessToken
          );
          $json = curlGet($url, $params);
          $menu = json_decode($json, true);

          $groups = array();
          foreach ($menu['groups'] as $group) {
            // echo $group['id'] . ': ' . $group['name'] . '<br>';
            $groups[$group['id']] = $group['name'];
          }

          $productCategories = array();
          foreach ($menu['productCategories'] as $category) {
            // echo $category['id'] . ': ' . $category['name'] . '<br>';
            $productCategories[$category['id']] = $category['name'];
          }

          echo 'Обновление меню ' . $menu['uploadDate'] . ', ревизия ' . $menu['revision'] . '<br>';          
        ?>
        </p>
        
        <h2>Меню</h2>
        <table id="table-menu" class="table table-sm table-hover table-striped table-bordered" style="width:100%">
          <thead>
            <tr>
              <th>Артикул
              <th>Название
              <th>Цена              
              <th>Изображения              
              <th>Группа
              <th>Категория
              <th>Описание
              <th>isIncludedInMenu
              <th>Вес, кг
              <th>Тип продукта
              <th>Энергетическая ценность
              <th>SEO
            </tr>
          </thead>
          <tbody>
          <?php
            $demoProduct = null;

            foreach ($menu['products'] as $product) {
              if ($demoProduct == null) $demoProduct = $product;
              
              echo '<tr>';
              echo '<td>' . $product['code'];
              echo '<td>' . $product['name'];
              echo '<td>' . $product['price'] . '&nbsp;&#8381;';
              echo '<td>';
              foreach ($product['images'] as $image)
                echo '<a target=_blank href="' . $image['imageUrl'] . '"><img width=200 src="' . $image['imageUrl'] . '"></a>';
  
              if (isset($product['parentGroup']))
                echo '<td>' . $groups[$product['parentGroup']];
              else 
                echo '<td>?';
  
              if (isset($product['productCategoryId']))
                echo '<td>' . $productCategories[$product['productCategoryId']];
              else 
                echo '<td>?';    
  
              echo '<td>' . $product['description'] . '<br><br>ID: ' . $product['id'];
              echo '<td>' . $product['isIncludedInMenu'];
              echo '<td>' . $product['weight'];
              echo '<td>' . $product['type'];
  
              // Энергетическая ценность
              echo '<td>' . 'Белки '        . round($product['fiberAmount']) . '<br>'
                          . 'Жиры '         . round($product['fatAmount']) . '<br>'
                          . 'Углеводы '     . round($product['carbohydrateAmount']) . '<br>'
                          . 'Калорийность ' . round($product['energyAmount']);

              // Для внешних интеграций поля SEO можно использовать для передачи различных данных
              // Например, можно таким образом передавать цену со статичной скидкой без необходимости подключения к iikoCard
              // https://docs.google.com/document/d/1pRQNIn46GH1LVqzBUY5TdIIUuSCOl-A_xeCBbogd2bE/edit#heading=h.d5wifi4jf2l2
              echo '<td>';
              if (isset($product['seoTitle']) && strlen($product['seoTitle']) > 0) 
                echo 'seoTitle: ' . $product['seoTitle'] . '<br>';
              if (isset($product['seoKeywords']) && strlen($product['seoKeywords']) > 0) 
                echo 'seoKeywords: ' . $product['seoKeywords'] . '<br>';
              if (isset($product['seoDescription']) && strlen($product['seoDescription']) > 0) 
                echo 'seoDescription: ' . $product['seoDescription'] . '<br>';
              if (isset($product['seoText']) && strlen($product['seoText']) > 0) 
                echo 'seoText: ' . $product['seoText'] . '<br>';
            }
          ?>
          </tbody>
          <tfoot>
            <tr>
              <th>Артикул
              <th>Название
              <th>Цена              
              <th>Изображения              
              <th>Группа
              <th>Категория
              <th>Описание
              <th>isIncludedInMenu
              <th>Вес, кг
              <th>Тип продукта
              <th>Энергетическая ценность
              <th>SEO
            </tr>
          </tfoot>          
        </table>
      
        <h2>Пример позиции меню</h2>
        <p>
        <?php 
          print_r($demoProduct); 
        ?>
        </p>
      </div>
    </div>
    
  <!-- page script -->
  <script>
    $(document).ready(function() {
        $('#table-menu').DataTable( {
          'dom': "<'row'<'col-sm-6'f><'col-sm-6'i>><'row'<'col-sm-12'tr>><'row'<'col-sm-12'i>>",
          'language': { 'url': '//cdn.datatables.net/plug-ins/1.10.19/i18n/Russian.json' },
          'pageLength': 1000,
          'order': [0, 'asc']
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