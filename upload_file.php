<?
$uploaddir = '';
$uploadfile = $uploaddir . basename($_FILES['FILE']['name']);


if (move_uploaded_file($_FILES['FILE']['tmp_name'], $uploadfile)) {
	$result = array(
		'status' => 'success',
		'path'   => '/local/services/ajax/upload/file/'.$uploadfile,
	);
} else {
    $result = array(
		'status'  => 'error',
		'message' => 'Ошибка загрузки файла',
	);
}

/*echo 'Некоторая отладочная информация:';
print_r($_FILES);
print_r($result);*/

echo json_encode($result);
?>
