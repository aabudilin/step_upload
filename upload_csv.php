<?require_once($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/include/prolog_before.php");
//Подключаем классы

require_once($_SERVER['DOCUMENT_ROOT']."/local/classes/debug.php");
$APPLICATION->SetTitle("Загрузка данных");

use Bitrix\Main\Loader;
Loader::includeModule("iblock");
Loader::includeModule("catalog");

//ID инфоблока
define("CATALOG_IBLOCK", 33);
define("PATH_CSV", $_SERVER['DOCUMENT_ROOT'].$_REQUEST['path']);
define('PATH_LOG', 'log.txt');

//Вывод лога
define('LOGGED', false);

$info = array(
  'new'    => 0,
  'update' => 0,
  'err'    => 0,
);

$log ='';

Debug::log(PATH_LOG,'Файл синхронизации запущен');

function to_utf8 ($arr) {
	if (is_array($arr)) {
	    $new_arr = array();
	    foreach ($arr as $item) {
	      $new_arr[] = iconv("Windows-1251", "UTF-8", trim($item));
	    }
	} else {
	    $new_arr = iconv("Windows-1251", "UTF-8", trim($arr));
	}
	return $new_arr;
}

//Функция создания раздела
function create_section($name, $parent = false) {
	$section = new CIBlockSection;
	$arFields = Array(
		"ACTIVE" => "Y", 
		"IBLOCK_ID" => CATALOG_IBLOCK,
		"NAME" => $name,
	);

	if ($parent) {
		$arFields["IBLOCK_SECTION_ID"] = $parent;
	}

	if ($id_section = $section->Add($arFields)) {
		return $id_section;
	} else {
		return $section->LAST_ERROR;
	}
}

//Функция создания элемента
function create_element($data) {
	$params = array(
	    "max_len" => "100", 
	    "change_case" => "L", 
	    "replace_space" => "_", 
	    "replace_other" => "_", 
	    "delete_repeat_replace" => "true", 
	    "use_google" => "false", 
	 );

	$el = new CIBlockElement;
	//$PROP['PROPERTY_BRANDS_REF'] = $data[4];
	$elArray = Array(
	    "IBLOCK_ID"         => CATALOG_IBLOCK,
	    "IBLOCK_SECTION_ID" => $data[3],       
	    "NAME"              => $data[1],
	    "ACTIVE"            => "Y",
	    "CODE"              => CUtil::translit($data[1], "ru", $params),
	    //"PROPERTY_VALUES"   => $PROP,
	);

	if($ID = $el->Add($elArray)) {
		//Записываем свойство типа справочник
		CIBlockElement::SetPropertyValuesEx($ID, CATALOG_IBLOCK, array('BRANDS_REF' => $data[4]));

		//Создаем товар
		$productID = CCatalogProduct::add(array("ID" => $ID, "QUANTITY" => 1));

		//Добавляем цену
		$arFields = Array(
		    "CURRENCY"         => "RUB", // валюта
		    "PRICE"            => intval($data[4]), // значение цены
		    "CATALOG_GROUP_ID" => 1, // ID типа цены
		    "PRODUCT_ID"       => $ID, // ID товара
		);

		CPrice::Add($arFields);

		//Количество по складам
		/*$arFields = Array(
			"PRODUCT_ID" => $productID,
		    "STORE_ID"   => $storeID,
		    "AMOUNT"     => $rest,
		)
		CCatalogStoreProduct::Add($arFields);/**/
		return $ID;
	} else {
	    return $el->LAST_ERROR;
	}
}

//Функция проверки существования
function isset_element($param, $val) {
	$arFilter = Array("IBLOCK_ID"=>CATALOG_IBLOCK, $param => $val);
	$res = CIBlockElement::GetList(Array(), $arFilter, false, Array("nPageSize"=>1), array('ID'));
	if ($ob = $res->GetNextElement()) {
	    $arFields = $ob->GetFields();
	    return $arFields['ID'];
	} else {
	    return false;
	}
}

function deactivate() {
	$ar_map = array();
	$arFilter = Array("IBLOCK_ID"=>CATALOG_IBLOCK);
	$res = CIBlockElement::GetList(Array(), $arFilter, false, array(), array('ID'));
	while ($ob = $res->GetNextElement()) {
	    $arFields = $ob->GetFields();
	    //Деактивируем элементы
	    $el = new CIBlockElement;
	    $ElementArray = Array("ACTIVE" => "N",);
	    $arFields = $ob->GetFields();
	    $el->Update($arFields['ID'], $ElementArray);
	}
}

//Функция обновления элемента
function update_element($data) {
	$el = new CIBlockElement;

	$arLoad = Array(
	    "IBLOCK_ID"           => CATALOG_IBLOCK,
	    "IBLOCK_SECTION_ID" => $data[3],       
	    "ACTIVE"         => "Y",
	);

  	if ($res = $el->Update($data[0], $arLoad)) {
    	$productID = CCatalogProduct::Update($data[0],array("PURCHASING_PRICE" => intval($data[6]), "QUANTITY" => 1));
    	$price = intval($data[5]);

    	//Добавляем цену
    	$arPropPrice = Array(
      		"CURRENCY"         => "RUB",       // валюта
      		"PRICE"            => $price,    // значение цены
      		"CATALOG_GROUP_ID" => 1,           // ID типа цены
      		"PRODUCT_ID"       => $data[0],  // ID товара
    	);

    	$res_price = CPrice::GetList(
        	array(),
        	array(
                "PRODUCT_ID" => $data[0],
                "CATALOG_GROUP_ID" => 1
            )
    	);

    	if ($arr = $res_price->Fetch()) {
      		CPrice::Update($arr["ID"],$arPropPrice);
      		//echo '<br />'.$data[1].'<br />';
      		//print_r($arr);
    	} else {
      		CPrice::Add($arPropPrice);
    	}
    	return true;
  	} else {
	  	return $el->LAST_ERROR;
  	}
}

function getRows($file) {
    $handle = fopen($file, 'rb');
    if (!$handle) {
        throw new Exception();
    }
   
    // пока не достигнем конца файла
    while (!feof($handle)) {
        // читаем строку
        // и генерируем значение
        yield fgets($handle);
    }
   
    // закрываем
    fclose($handle);
}

/*-------Настройки-------*/

//Сопоставление неизменяемых полей
$massProp = array(
    '2' => 'ARTICLE', //IE_CODE - Артикул 1
    '3' => 'CODE_2', //IP_PROP7 - Артикул 2
    '4' => 'COUNTRY', //IP_PROP16 - Страна
    '18' => 'BRAND',
    '20' => 'BRAND_2',
    '22' => 'NAME_IMG_FILE'
);

  //Массив для удаления пробелов
  //$mass_trim = array(4);

  //Сопоставление под статус
  /*$status = array {
    '1' => '5', //Свободна
    '2' => '6', //Продана
    '3' => '7'  //Зарезервирована
  }*/

//Получение списка секций
$mass_section = array();
$res = CIBlockSection::GetList(
    Array('LEFT_MARGIN' => 'ASC'), 
    Array("IBLOCK_ID"=>CATALOG_IBLOCK, "ACTIVE"=>"Y"), 
    true,
    Array('ID','NAME','IBLOCK_SECTION_ID','DEPTH_LEVEL')
);

while($arSection = $res->GetNext()) {
    $mass_section[$arSection['NAME']] = $arSection;
};


  //Получение списка свойств
  /*$mass_props = array();
  $properties = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID"=>$iblock_id));
  while ($prop_fields = $properties->GetNext())
  {
    $mass_props[$prop_fields['NAME']] = array (
      'CODE' => $prop_fields['CODE'],
      'ID' => $prop_fields['ID'],
    );
  }*/

$ok = false;
//--Получение файла
setlocale(LC_ALL, 'ru_RU');
setlocale(LC_TIME, 'ru_RU.UTF-8');
setlocale(LC_NUMERIC, "en_US.utf8");
$row = 0;
$start = $_REQUEST['step'] * $_REQUEST['amount'];
$end = $start + $_REQUEST['amount'];
$count_job = 0;
$err = false;
$break = false;

//echo '<p>Старт - '.$start.'; Конец - '.$end.'</p>';
$type_file = substr(PATH_CSV,-3);

if (PATH_CSV !== null && $type_file !='csv') {
  	$err = 'Некорректный тип файла. Для загрузки необходим файл с расширением csv';
} else {
	if (file_exists(PATH_CSV)) {
			foreach (getRows(PATH_CSV) as $data) {
      			if ($row >= $start) {
      				$data = to_utf8(explode(';',$data));
        			
					$PROP_ADD = array();
               
        			if (!empty($data[0])) {
            			update_element($data);
            			if(LOGGED) $log .= 'Обновление элемента - '.implode(' - ',$data).'<br />';
            			$info['update']++;
        			} else {
            			$result = create_element($data);
            			if (is_numeric($result)) {
              				if(LOGGED) $log .= 'Добавлен элемент - '.implode(' - ',$data)."<br />";
              				$info['new']++;
            			} else {
              				if(LOGGED) $log .= 'Ошибка добавления элемента - '.implode(' - ',$data).' / '.$result.'<br />';
              				$info['err']++;
            			}
        			}
        			$count_job++;
      			}
      			$row++;
      			if ($row > $end)  {
        			$break = true;
        			break;
      			}
    		}

  		} else {
    		$err = 'Файл отсутствует';
  		}
}

//Обработка
if ($err == false) $step++;

if ($step == 50 || $break == false) {
	$end = true;
} else {
	$end = false;
}



//Возвращаем сколько обработано и результат
if (!$err) {
	$result = array (
		'status' => 'success',
		'end' => $end,
		'job' => $count_job,
		'row' => $row,
	);
} else {
	$result = array (
		'status' => 'error',
		'message' => $err,
	);
}

echo json_encode($result);
?>
