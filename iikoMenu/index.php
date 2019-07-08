<?php

/*
 * Выгрузка меню ресторана
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

// Получение меню
$url = "https://$server:$port/api/0/nomenclature/$orgGuid";
$params = array(
    'access_token' => $accessToken
);
$json = curlGet($url, $params);
$menu = json_decode($json, true);

echo "<br><br>Группы меню<br>";
$groups = array();
foreach ($menu['groups'] as $group) {
    echo $group['id'] . ': ' . $group['name'] . '<br>';
    $groups[$group['id']] = $group['name'];
}

echo "<br>Категории блюд<br>";
$productCategories = array();
foreach ($menu['productCategories'] as $category) {
    echo $category['id'] . ': ' . $category['name'] . '<br>';
    $productCategories[$category['id']] = $category['name'];
}

echo "Меню";

?>

<style>
  th, td { border: 1px solid #ccc; }
</style>        
<table>
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
    
<?php

foreach ($menu['products'] as $product) {
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

</table>

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
    
    echo 'curlGet: <a href="' . $options[CURLOPT_URL] . '">' . $options[CURLOPT_URL] . '</a><br>';
    
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

?>