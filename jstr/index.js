let boxes = document.querySelectorAll('.container-main div');
let step = 0;
let won = -1;
const audioBtn = document.querySelector('.play');

const wonAudio = new Audio('./assets/tracks/Ku.mp3');
const mainAudio = new Audio('./assets/tracks/tr1.mp3');
const stepHtml = document.querySelector('[data-step]'); 
mainAudio.addEventListener('ended', function() {
    this.currentTime=0;
    this.play();
});

let playMusic = 1;
let playTimeLast=0;
let results=[];


function saveLocalStorage() {
    localStorage.setItem('playMusic', playMusic);
    localStorage.setItem('playTimeLast', mainAudio.currentTime);
    localStorage.setItem('results', JSON.stringify(results));
  }
window.addEventListener('beforeunload', saveLocalStorage);
// saveLocalStorage();

function playMusicAction () {
    if (playMusic == 1) {
        //console.log(playMusic);
        mainAudio.play();
        if(!mainAudio.paused) audioBtn.classList.add('pause');
    };
}

function musicToggle(){
    if (playMusic == 1) {
        playMusic = 0;
        mainAudio.pause();
        audioBtn.classList.remove('pause');
    }
    else {
        playMusic = 1;
        mainAudio.play();
        audioBtn.classList.add('pause');
    }
}

audioBtn.addEventListener('click', musicToggle);

window.addEventListener('click', () => {
    playMusicAction();
},
{once: true}
);

function loadLocalStorage() {
    
    if(localStorage.getItem('playMusic')) {
        playMusic = localStorage.getItem('playMusic')*1;
        console.log(playMusic+'--')
        playTimeLast = localStorage.getItem('playTimeLast');
            mainAudio.currentTime = playTimeLast;

        // console.log(playMusic + '-'+ playTimeLast);
        if (playMusic) {
            playMusicAction();
        }
    }
    if(localStorage.getItem('results')) {
        results = JSON.parse(localStorage.getItem('results'));
    }
}
window.addEventListener('load', () => {
    loadLocalStorage();
    showHistory(results);
});




const winCombination = [
    [0,1,2],
    [3,4,5],
    [6,7,8],
    [0,3,6],
    [1,4,7],
    [2,5,8],
    [0,4,8],
    [2,4,6]
];

const winStyles = [
    'wonright',
    'wonright',
    'wonright',
    'wondown',
    'wondown',
    'wondown',
    'woncross1',
    'woncross2'
]

document.querySelector('.container-main').addEventListener('click', e => {
    if (won != -1 ) {
        return false;
    }
    if(e.target.innerText != '') return null;
    step++;
    stepHtml.innerText=step;

    if (step %2 != 0) {
        e.target.innerText='X';
        e.target.classList.add('red');
    }
    else {
        e.target.innerText='0';
        e.target.classList.add('blue');
    }
    checkWin();
});


function checkWin() {
    if(step>=9 && won == -1) {
        //alert('Draw');
        results.push(['-', 10]);
        showHistory(results);
        document.querySelector('.new-game').innerText='Ничья !'
        window.setInterval(() => {
            document.querySelector('.new-game').classList.toggle('shadow');
            document.querySelector('.container-main').classList.toggle('shadow');
        }, 1000);
        return false;
    }
    
    //console.log(ifWon('X'));
    let x = ifWon('X');
    if (x!= -1) {
        won = 'X';
        //console.log('index='+x+' div='+winCombination[x][0]+' style='+winStyles[x]);
        boxes[winCombination[x][0]].classList.add(winStyles[x]);
        window.setTimeout(() => {
            boxes[winCombination[x][0]].classList.add('active');
        }, 50);
        window.setInterval(() => {
            boxes[winCombination[x][0]].classList.toggle('active');
        }, 1000);
        
        console.log('Выиграли '+won+' !');
    }

    let y = ifWon('0')
    if (y != -1) {
        won = '0';
        boxes[winCombination[y][0]].classList.add(winStyles[y]);
        window.setTimeout(() => {
            boxes[winCombination[y][0]].classList.add('active');
        }, 50);
        window.setInterval(() => {
            boxes[winCombination[y][0]].classList.toggle('active');
        }, 1000);
    }
    

    if(won == 'X') document.querySelector('[data-winner]').innerText = 'крестики';
    if(won == '0') document.querySelector('[data-winner]').innerText = 'нолики';
    if(won != -1) {
        document.querySelector('.new-game').classList.add('hide');
        document.querySelector('.won').classList.remove('hide');
        wonAudio.play();
        
        results.push([won, step]);
        showHistory(results);
        
        //console.log(results);
    }
}

function ifWon($x) {
    let rez = -1;

    winCombination.forEach((element, index) => {
        // console.log(boxes[element[0]].innerText);
        if (boxes[element[0]].innerText == $x && boxes[element[1]].innerText == $x && boxes[element[2]].innerText == $x) {
            // console.log('true');
            rez = index;
        }
    });

    return rez;
}

document.querySelector('.info button').addEventListener('click', () => {
    location.reload();
});


function showHistory(arr) {
    if(arr.length > 10) arr.shift();
     
    arr.sort((a,b) => {
        return a[1]-b[1];
    });

    let rez = '';
    arr.forEach((item, index) => {
        if(item[0] == '-') {
            rez += `<span>${index+1}-е место: ничья</span>`;    
        }
        else {
            rez += `<span>${index+1}-е место: выиграли "${item[0]}" за ${item[1]} ходов</span>`;
        }
    });
    if(arr.length > 10) arr.shift();
    document.querySelector('.result-list').innerHTML= rez;
}
