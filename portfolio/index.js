const i18Obj = {
  'en': {
    'skills': 'Skills',
    'portfolio': 'Portfolio',
    'video': 'Video',
    'price': 'Price',
    'contacts': 'Contacts',
    'hero-title': 'Alexa Rise',
    'hero-text': 'Save sincere emotions, romantic feelings and happy moments of life together with professional photographer Alexa Rise',
    'hire': 'Hire me',
    'skill-title-1': 'Digital photography',
    'skill-text-1': 'High-quality photos in the studio and on the nature',
    'skill-title-2': 'Video shooting',
    'skill-text-2': 'Capture your moments so that they always stay with you',
    'skill-title-3': 'Rotouch',
    'skill-text-3': 'I strive to make photography surpass reality',
    'skill-title-4': 'Audio',
    'skill-text-4': 'Professional sounds recording for video, advertising, portfolio',
    'winter': 'Winter',
    'spring': 'Spring',
    'summer': 'Summer',
    'autumn': 'Autumn',
    'price-description-1-span-1': 'One location',
    'price-description-1-span-2': '120 photos in color',
    'price-description-1-span-3': '12 photos in retouch',
    'price-description-1-span-4': 'Readiness 2-3 weeks',
    'price-description-1-span-5': 'Make up, visage',
    'price-description-2-span-1': 'One or two locations',
    'price-description-2-span-2': '200 photos in color',
    'price-description-2-span-3': '20 photos in retouch',
    'price-description-2-span-4': 'Readiness 1-2 weeks',
    'price-description-2-span-5': 'Make up, visage',
    'price-description-3-span-1': 'Three locations or more',
    'price-description-3-span-2': '300 photos in color',
    'price-description-3-span-3': '50 photos in retouch',
    'price-description-3-span-4': 'Readiness 1 week',
    'price-description-3-span-5': 'Make up, visage, hairstyle',
    'order': 'Order shooting',
    'standard': 'Standard',
    'premium': 'Premium',
    'gold': 'Gold',
    'contact-me': 'Contact me',
    'send-message': 'Send message',
    'email': 'Е-mail',
    'phone': 'Phone',
    'message': 'Message'
  },
  'ru': {
    'skills': 'Навыки',
    'portfolio': 'Портфолио',
    'video': 'Видео',
    'price': 'Цены',
    'contacts': 'Контакты',
    'hero-title': 'Алекса Райс',
    'hero-text': 'Сохраните искренние эмоции, романтические переживания и счастливые моменты жизни вместе с профессиональным фотографом',
    'hire': 'Пригласить',
    'skill-title-1': 'Фотография',
    'skill-text-1': 'Высококачественные фото в студии и на природе',
    'skill-title-2': 'Видеосъемка',
    'skill-text-2': 'Запечатлите лучшие моменты, чтобы они всегда оставались с вами',
    'skill-title-3': 'Ретушь',
    'skill-text-3': 'Я стремлюсь к тому, чтобы фотография превосходила реальность',
    'skill-title-4': 'Звук',
    'skill-text-4': 'Профессиональная запись звука для видео, рекламы, портфолио',
    'winter': 'Зима',
    'spring': 'Весна',
    'summer': 'Лето',
    'autumn': 'Осень',
    'price-description-1-span-1': 'Одна локация',
    'price-description-1-span-2': '120 цветных фото',
    'price-description-1-span-3': '12 отретушированных фото',
    'price-description-1-span-4': 'Готовность через 2-3 недели',
    'price-description-1-span-5': 'Макияж, визаж',
    'price-description-2-span-1': 'Одна-две локации',
    'price-description-2-span-2': '200 цветных фото',
    'price-description-2-span-3': '20 отретушированных фото',
    'price-description-2-span-4': 'Готовность через 1-2 недели',
    'price-description-2-span-5': 'Макияж, визаж',
    'price-description-3-span-1': 'Три локации и больше',
    'price-description-3-span-2': '300 цветных фото',
    'price-description-3-span-3': '50 отретушированных фото',
    'price-description-3-span-4': 'Готовность через 1 неделю',
    'price-description-3-span-5': 'Макияж, визаж, прическа',
    'standard': 'Стандарт',
    'premium': 'Премиум',
    'gold': 'Золото',
    'order': 'Заказать съемку',
    'contact-me': 'Свяжитесь со мной',
    'send-message': 'Отправить',
    'email': 'Электронная почта',
    'phone': 'Телефон',
    'message': 'Сообщение'
  }
}

let lang = 'en';
let theme = 'dark';

window.addEventListener('load', getLocalStorage)

function setLocalStorage() {
  localStorage.setItem('lang', lang);
  localStorage.setItem('theme', theme);
}
window.addEventListener('beforeunload', setLocalStorage)

function getLocalStorage() {
  if(localStorage.getItem('lang')) {
    lang = localStorage.getItem('lang');
    theme = localStorage.getItem('theme');

    langChange(lang);
    document.querySelectorAll('.a-lang').forEach((item) => {
      if(item.dataset.lang==lang) {
        item.classList.add('lang-active');
      }
      else {
        item.classList.remove('lang-active');
      }
    });

    if (theme=='light') {
      lightSwitch();
      document.querySelector('.light-shift').classList.toggle('light');
    }
  }
}

function hamburger () {
  //alert ('click');
  document.querySelector('.hamburger').classList.toggle('is-active');
  document.querySelector('.top-nav').classList.toggle('show-menu');
}

function closeMenu () {
  document.querySelector('.hamburger').classList.remove('is-active');
}

document.querySelector('.hamburger').addEventListener('click', hamburger ,false);

const links = document.querySelectorAll('.nav-link');
links.forEach((el) => el.addEventListener('click', hamburger ,false));


//console.log('Вёрстка соответствует макету. Ширина экрана 768px +48\nблок <header> +6\nсекция hero +6\nсекция skills +6\nсекция portfolio +6\nсекция video +6\nсекция price +6\nсекция contacts +6\nблок <footer> +6\nНи на одном из разрешений до 320px включительно не появляется горизонтальная полоса прокрутки. Весь контент страницы при этом сохраняется: не обрезается и не удаляется +15\nнет полосы прокрутки при ширине страницы от 1440рх до 768рх +5\nнет полосы прокрутки при ширине страницы от 768рх до 480рх +5\nнет полосы прокрутки при ширине страницы от 480рх до 320рх +5\nНа ширине экрана 768рх и меньше реализовано адаптивное меню +22\nпри ширине страницы 768рх панель навигации скрывается, появляется бургер-иконка +2\nпри нажатии на бургер-иконку справа плавно появляется адаптивное меню, бургер-иконка изменяется на крестик +4\nвысота адаптивного меню занимает всю высоту экрана. При ширине экрана 768-620рх вёрстка меню соответствует макету, когда экран становится уже, меню занимает всю ширину экрана +4\nпри нажатии на крестик адаптивное меню плавно скрывается уезжая за правую часть экрана, крестик превращается в бургер-иконку +4\nбургер-иконка, которая при клике превращается в крестик, создана при помощи css-анимаций без использования изображений +2\nссылки в адаптивном меню работают, обеспечивая плавную прокрутку по якорям +2\nпри клике по ссылке в адаптивном меню адаптивное меню плавно скрывается, крестик превращается в бургер-иконку +4');

document.querySelector('.portfolio-cat-list').addEventListener('click', changePics);

const portfolioBtns = document.querySelectorAll('.portfolio-btn');
const portfolioPics = document.querySelectorAll('.portfolio-item');
const portfolioLis = document.querySelectorAll('.portfolio-cat-list li');
function changePics(event){
  if(event.target.classList.contains('portfolio-btn')){

    portfolioLis.forEach((item) => item.classList.remove('active'));
    event.target.parentNode.classList.add('active');

    portfolioPics.forEach((item, index) => {
      item.src=`./assets/img/${event.target.dataset.season}/${index+1}.jpg`;
    });
  }
}

function preLoadImages(){

  const seasons = ['winter', 'summer', 'autumn', 'spring'];
  seasons.forEach((item) => {
    for (let i = 1; i <=6; i++) {
      const img = new Image();
      img.src=`./assets/img/${item}/${i}.jpg`;
    }
  });
  preLoadImages();

}

const langEls = document.querySelectorAll('[data-i18]');

function langChange(lang) {
  langEls.forEach ((item) => {
    if(item.placeholder) {
      item.placeholder = i18Obj[lang][item.dataset.i18];
    }
    else {
      item.innerText = i18Obj[lang][item.dataset.i18];
    }
  });
}

document.querySelectorAll('.a-lang').forEach((item) => {
  item.addEventListener('click', langChangeAction);
});

function langChangeAction(event) {
  event.preventDefault();
  lang = event.target.dataset.lang;
  langChange(lang);

  document.querySelectorAll('.a-lang').forEach((item) => {
    item.classList.toggle('lang-active');
  });
}

document.querySelector('[data-theme-btn]').addEventListener('click', lightSwitchAction);

const themeEls = document.querySelectorAll('[data-theme]');
const h2Lines = document.querySelectorAll('.section-title');

function lightSwitchAction(event){

  event.target.classList.toggle('light');
  if (theme=='dark') theme = 'light';
  else theme = 'dark';

  lightSwitch();

}

function lightSwitch(){

  document.querySelector('body').classList.toggle('light-theme');
  h2Lines.forEach((item) => {
    item.classList.toggle('section-title-light');
  });

  document.querySelectorAll('.portfolio-cat-list').forEach((item) => {
    item.classList.toggle('portfolio-cat-list-light');
  });

  document.querySelector('.top-nav').classList.toggle('top-light');

  document.querySelectorAll('.hamburger').forEach((item) => {
    item.classList.toggle('hamburger-light');
  });


  themeEls.forEach((item) => {
    item.classList.toggle('light-theme');
  });

}



const buttons = document.querySelectorAll('button');

buttons.forEach((button) => {
  button.addEventListener('click', function (e) {
    const x = e.clientX
    const y = e.clientY

    const buttonTop = e.target.getBoundingClientRect().top;
    const buttonLeft = e.target.getBoundingClientRect().left;

    const xInside = x - buttonLeft
    const yInside = y - buttonTop

    const circle = document.createElement('span')
    circle.classList.add('circle')
    circle.style.top = yInside + 'px'
    circle.style.left = xInside + 'px'

    this.appendChild(circle)

    setTimeout(() => circle.remove(), 500)
  })
});

