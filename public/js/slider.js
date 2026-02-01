const slides = document.querySelectorAll('.slide');
const btns = document.querySelectorAll('.btnkr');
let currentSlideNum = 1;
let timerId;

const manualNav = function (manual) {
    currentSlideNum = manual+1;
    if (currentSlideNum > slides.length-1) {
        currentSlideNum = 0;
    }

    stopSlider();

    slides.forEach((slide) => {
        slide.classList.remove('active');
    });

    btns.forEach((btn) => {
        btn.classList.remove('active');
    });

    slides[manual].classList.add('active');
    btns[manual].classList.add('active');

    repeat();
}

btns.forEach((btn, i) => {
    btn.addEventListener("click", () => {
        manualNav(i);
    })
});

function stopSlider(){
    clearTimeout(timerId);
}

// document.addEventListener('keydown', (e) => {
//     if (e.key == 'Enter'){
//         stopSlider();
//     }
// });

const repeat = function (activeClass) {
    let active = document.getElementsByClassName('active');
    let i = currentSlideNum;

    const repeater = () => {
        console.log(currentSlideNum);
        timerId = setTimeout(function() {
            [...active].forEach((activeSlide) => {
                activeSlide.classList.remove('active');
            });

            slides[i].classList.add('active');
            btns[i].classList.add('active');
            i++;

            if (slides.length == i) {
                i = 0;
            }
            if(i >= slides.length) {
                return;
            }
            currentSlideNum = i;
            repeater();
        }, 4500);
    }
    repeater();
}
repeat();











