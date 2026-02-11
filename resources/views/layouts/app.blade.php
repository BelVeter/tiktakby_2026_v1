@php
  $v = 176;
@endphp

<!DOCTYPE html>
<html lang="{{(request()->lang ? request()->lang : 'ru')}}">

<head>
  <!-- Global site tag (gtag.js) - Yandex webmaster -->
  <meta name="yandex-verification" content="61541709f8b93408" />
  <!-- Global site tag (gtag.js) - Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=UA-15543442-1"></script>

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-WWTHNS0FYG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());

    gtag('config', 'G-WWTHNS0FYG');
  </script>

  <!-- Yandex.Metrika counter -->
  <script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments) };
      m[i].l = 1 * new Date(); k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
    })
      (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

    ym(4170664, "init", {
      clickmap: true,
      trackLinks: true,
      accurateTrackBounce: true,
      webvisor: true
    });
  </script>
  <noscript>
    <div><img src="https://mc.yandex.ru/watch/4170664" style="position:absolute; left:-9999px;" alt="" /></div>
  </noscript>
  <!-- /Yandex.Metrika counter -->

  {{--
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">--}}
  <link href="/public/css/bootstrap.min.css?v={{$v}}" rel="stylesheet" crossorigin="anonymous">

  <link rel="stylesheet" href="/public/css/app.css?v={{$v}}">

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="@yield('meta-description')">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" media="print"
    onload="this.media='all'">
  <link
    href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'" />

  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link
      href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
      rel="stylesheet">
    <link
      href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap"
      rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />
  </noscript>
  @yield('style')
  @yield('canonical')


  <link rel="icon" href="/tiktak.ico" type="image/x-icon">
  <link rel="icon" type="image/png" href="/public/favicon-32x32.png" sizes="32x32">
  {{--
  <link rel="icon" type="image/png" href="/public/favicon-32x32.png" sizes="16x16">--}}
  {{--
  <link rel="icon" type="image/png" href="/public/favicon-32x32.png" sizes="64x64">--}}
  <link rel="apple-touch-icon" sizes="32x32" href="/public/favicon-32x32.png">

  <title>@yield('page-title')</title>
</head>

<body>
  <div class="general-wrapper">
    <div id="shadow"></div>
    @include('includes.header')
    {{-- <div class="row">--}}
      {{-- <div class="alert alert-danger text-center" role="alert">--}}
        {{-- Внимание! 7 ноября работает только салон по адресу Ложинская, 5 с 11:00 до 15:00.--}}
        {{-- </div>--}}
      {{-- </div>--}}
    <main>
      <!-- Main content1-->
      @yield('content')
    </main>

    @include('includes.footer')


    <!-- Bootstrap  -->
    {{--
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
      integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
      crossorigin="anonymous"></script>--}}
    {{--
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
      integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
      crossorigin="anonymous"></script>--}}
    {{--
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns"
      crossorigin="anonymous"></script>--}}

    {{--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"
      integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p"
      crossorigin="anonymous"></script>--}}
    <script src="/public/js/popper.min.js?v={{$v}}" crossorigin="anonymous"></script>
    {{--
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"
      integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF"
      crossorigin="anonymous"></script>--}}
    <script src="/public/js/bootstrap.min.js?v={{$v}}" crossorigin="anonymous"></script>

    <script src="/public/js/app.js?v={{$v}}"></script>
  </div> {{-- end of general wrapper--}}
</body>

</html>