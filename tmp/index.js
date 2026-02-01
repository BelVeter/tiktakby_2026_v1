// $(document)`.`ready(function(){
//   $(".hamburger").click(function(){
//     $(this).toggleClass("is-active");
//   });
// });

function hamburger () {
  //alert ('click');
  document.querySelector('.hamburger').classList.toggle('is-active');
  document.querySelector('.top-nav').classList.toggle('show-menu');
}

document.querySelector('.hamburger').addEventListener('click', hamburger ,false);
