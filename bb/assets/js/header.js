
document.querySelectorAll('.call-back-link').forEach((el) => {
    el.addEventListener('click', zv_show);
});

document.querySelector('#gr_fone').addEventListener('click', (e) => {
    cans_zv();
});

//office show/hide
document.querySelectorAll('.address-line li:not(.call-back-link)').forEach((el) => {
    el.addEventListener('mouseover', officeShow);
    el.addEventListener('onclick', officeShow);
    el.addEventListener('mouseleave', officeHide);
});

function officeShow(e){
    let modal = e.target.querySelector('div.office-top-container');
    modal.classList.add('show');
}
function officeHide(e){
    let modal = e.target.querySelector('div.office-top-container');
    modal.classList.remove('show');
}