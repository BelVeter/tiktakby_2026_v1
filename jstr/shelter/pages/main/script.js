document.querySelector('.nav-a.active').addEventListener('click', (e) => {
  e.preventDefault();
});
document.querySelector('.bank-a').addEventListener('click', (e) => {
  e.preventDefault();
});

document.querySelectorAll('.nav-a').forEach((el)=>{
  el.addEventListener('click', menuClose)
});


let hamburger = document.querySelector('.hamburger');
let menu = document.querySelector('.nav-menu');
let nav = document.querySelector('nav');
let blackout = document.querySelector('.blackout');
hamburger.addEventListener('click', menuToggle);

function menuToggle(){
  hamburger.classList.toggle('active');
  menu.classList.toggle('show');
  nav.classList.toggle('menu-shown');
  blackout.classList.toggle('show');
}

function menuClose(){
  hamburger.classList.remove('active');
  menu.classList.remove('show');
  nav.classList.remove('menu-shown');
  blackout.classList.remove('show');
}
