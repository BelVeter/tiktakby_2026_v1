@php /** @var \App\MyClasses\KBronLine $l */ @endphp

<div class="l3_kb_lines_container">
  <div class="item">
    <div class="tovar-info">
      <span class="inv-n">Инв. номер {{$l->getInvNFormated()}}</span>
      <span class="rost">Рост: {{$l->getTovar()->getRostFrom()}}/{{$l->getTovar()->getRostTo()}}</span>
      <span class="age">Возраст: {{$l->getTovar()->getItemSize()}}</span>
    </div>
    <div class="line-container" style="width: {{$l->getLineWidthInPixels()}}px;">
      <div class="line" style="width: {{$l->getLineWidthInPixels()}}px;">

        @foreach($l->getDayCirclesCss() as $circle)
          <span class="circle {{$circle[1]}}" style="left: {{$circle[0]}}px;"></span>
        @endforeach

        @foreach($l->getFreePeriodsCssArray() as $fp)
          <span class="free-period" style="left: {{$fp[0]}}px; width: {{$fp[1]}}px;"></span>
        @endforeach
      </div>
    </div>
    <div class="time-btn-div">
      <button>Выбрать время</button>
    </div>
    <input type="datetime-local">
  </div>
</div>
