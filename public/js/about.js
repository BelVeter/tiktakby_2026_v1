
let observer = new IntersectionObserver((entires)=>{
    entires.forEach(entry=>{
        // console.log(entry.target);
        let maxStepNum=150;
        let span = entry.target;
        let period = Math.round (+span.dataset.time/+span.dataset.end);
        if (+span.dataset.end>maxStepNum) {
            period = Math.round (+span.dataset.time/maxStepNum);
        }
        span.innerHTML='0';
        let x = {};
        x.interval = setInterval(countUp, period, span, x, maxStepNum);
    })
}, {threshold: [1]});

document.querySelectorAll('.count-up-span').forEach(el=>{
    //el.addEventListener('click', startCountUp)
    observer.observe(el);
});


function countUp(span, x, maxStepNum) {
    let step = 1;
    let start = +span.innerText || 0;
    let end = +span.dataset.end || 0;

    if (end>maxStepNum) {
        step=Math.round(end/maxStepNum);
    }

    start += step;
    if (start>end) start=end;
    span.innerText=start;
    if (start >= end) clearInterval(x.interval);
}



