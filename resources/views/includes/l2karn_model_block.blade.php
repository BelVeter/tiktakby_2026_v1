@php /** @var \App\MyClasses\L2ModelWeb $l2 */ @endphp

<div class="l2karn-card_container">
  <img class="l2heart" src="/public/png/l2heart.png" alt="favorite">
  <a href="{{$l2->getL3Url(request()->lang)}}" class="l2karn-card_img-a-container">
    <img src="{{ $l2->getPicUrl(request()->lang) }}" class="l2karn-card_img" alt="{{$l2->getPicAltText()}}">
    <div class="l2karn-card_line-2">
      <span>{!! $l2->getNameNoBr(29) !!}</span>
      <input type="hidden" data-mid="{{$l2->getModelId()}}" data-mwid="{{$l2->getModelWeb()->getWebId()}}">
    </div>
    <div class="l2karn-card_line-3">
      <div class="l2-col1">{{ number_format($l2->getDayTarifValue(), 2, ',', ' ') }} BYN / сутки</div>
    </div>
    <div class="l2karn-card_line-4">
      <div class="name">Размер</div>
      <div class="l2-col2" title="размеры">{{ implode(', ', $l2->getHighLevelsOfRostArray()) }}</div>
    </div>
  </a>
</div>
