<?require_once($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/include/prolog_before.php");?>
<form action="#" method="post" enctype="multipart/form-data" id="form">
  <p>Выберите файл для загрузки</p>
  <p><input type="file" name="FILE" /></p>
  <p><button type="submit">Загрузить</button></p>
</form>

<div id="result"></div>

<script>

let amount = 200;

form.addEventListener('submit', async function(e) {
	//Загрузка файла
	e.preventDefault();
	const formData = new FormData(this);
	console.log(formData);
	const response = await fetch('/local/services/ajax/upload/file/', {
		method: 'POST',
		body: formData
	});
	const result = await response.json();
	console.log(result);
	if(result.status == 'success') {
		stepAjax(0,amount,result.path);
	} else {
		result.innerHTML = result.message;
	}
});


	//start.onclick = () => stepAjax(1,amount);

async function stepAjax(step,amount,pathcsv) {
	let url = '/local/services/ajax/upload/csv/?step=' + step + '&amount=' + amount + '&path=' + pathcsv;
	console.log(url);
	let response = await fetch(url);
	if (step == 0) render('Файл загружен. Начинаем обработку');
	if (response.ok) {
		let json = await response.json();
		console.log(json);
		if (json.end === false) {
			step = step + 1;
			render('Шаг ' + step + ' закончен, запущена дальнейшая обработка');
			stepAjax(step,amount,pathcsv);
		} else {
			render('Шаг ' + step + ' закончен, обработка закончена');
		}
	} else {
		render("Ошибка HTTP: " + response.status);
	}
}

function render(str) {
	result.innerHTML = str;
}

</script>

