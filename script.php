<?php
// Принимает $_GET запрос от клиента
if (isset($_GET['adr']) && strlen($_GET['adr'])) {

  $adr = $_GET['adr'];

  // Пользователь может ввести всё что угодно, но ему могут быть интересны адреса только из Москвы
  if (!preg_match('/москва/ui', $adr)) {
    $adr = 'Москва, ' . $adr;
  }

  $xml = simplexml_load_file('http://geocode-maps.yandex.ru/1.x/?geocode=' . urlencode($adr));
  $found = $xml->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found;

  //  Возвращать пустой массив, если результатов не найдено
  $address_array = array();

  if ($found > 0) {

    foreach ($xml->GeoObjectCollection->featureMember as $item) { 

      $country = $item->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country;
      if ((string) $country->CountryName !== 'Россия') continue;

      $locality = $country->AdministrativeArea->Locality;

      // Пользователь может ввести всё что угодно, но ему могут быть интересны адреса только из Москвы (доп. проверка)
      if ((string) $locality->LocalityName !== 'Москва') continue;

      $coordinates = str_replace(' ', ',', $item->GeoObject->Point->pos);

      // Информация по району города...
      $subAdministrativeAreaName = $country->AdministrativeArea->SubAdministrativeArea->SubAdministrativeAreaName;
      if (is_null($subAdministrativeAreaName)) {
        $xmlArea = simplexml_load_file('http://geocode-maps.yandex.ru/1.x/?geocode=' . $coordinates . '&kind=district');
        if ($xmlArea->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found > 0) {
          $areas = array();
          foreach ($xmlArea->GeoObjectCollection->featureMember as $itemArea) {
            $areas[] = $itemArea->GeoObject->name;
          }
          if (count($areas) > 0) {
            $subAdministrativeAreaName = implode(', ', $areas);
          }
          else {
            $subAdministrativeAreaName = null;
          }
        }
      }
      // улице...
      $thoroughfareName = $locality->Thoroughfare->ThoroughfareName;
      // дому
      $premiseNumber = $locality->Thoroughfare->Premise->PremiseNumber;

      $address_array[] = array(
        'address_line' => (string) $country->AddressLine,
        'coordinates' => $coordinates,
        'subAdministrativeAreaName' => ($subAdministrativeAreaName === null) ? null : (string) $subAdministrativeAreaName,
        'thoroughfareName' => ($thoroughfareName === null) ? null : (string) $thoroughfareName,
        'premiseNumber' => ($premiseNumber === null) ? null : (string) $premiseNumber
      );

      // Если результатов несколько, то возвращать максимум 10
      if (count($address_array) >= 10) break;
    }

    if (count($address_array) > 0) {

      for ($i = 0; $i < count($address_array); $i++) {

        // Ближайшие пять станций метро
        $xml = simplexml_load_file('http://geocode-maps.yandex.ru/1.x/?geocode=' . $address_array[$i]['coordinates'] . '&kind=metro&results=5');
        $found = $xml->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found;

        if ($found > 0) {

          $address_array[$i]['metro'] = array();

          foreach ($xml->GeoObjectCollection->featureMember as $item) {
            $address_array[$i]['metro'][] =
              (string) $item->GeoObject
                ->metaDataProperty
                ->GeocoderMetaData
                ->AddressDetails
                ->Country
                ->AdministrativeArea
                ->Locality
                ->Thoroughfare
                ->Premise
                ->PremiseName;
          }

        }
      }
    }
  }
  /*echo '<pre>';
  var_dump($address_array);
  echo '</pre>';*/
  return $address_array;
}
else {
  echo 'Enter the address!';
}