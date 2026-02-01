let x = document.getElementById("demo");

function getLocation() {
    x.innerHTML = 'Идет определение позиции. Подождите секунд 10-20.';
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
    } else {
        x.innerHTML = "Геолокация не подерживается вашим браузером.";
    }
}

function showPosition(position) {
    //console.log(position.coords.latitude);
    let rez =`<a target="_blank" href="https://maps.google.com?daddr=${position.coords.latitude},${position.coords.longitude}">эта ссылка покажет на гугл картах где ты находишься.</a>`;
    //console.log(rez);
    x.innerHTML = rez;
}


document.querySelector('.shift_open_btn').addEventListener('click', openShift);


btn = document.querySelector('.shift_open_btn');

function openShift(e){
    e.preventDefault();
    btn.value = 'Ожидайте, идет открытие смены ...';
    btn.disabled = true;


    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(initiateOpenShift);
    }
    else console.log('some problems with navigation');
}

function initiateOpenShift(position){
    document.querySelector('#geo').value = position.coords.latitude + ',' +position.coords.longitude;
    document.querySelector('#shift_form').submit();

}