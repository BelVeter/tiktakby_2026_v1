@php /** @var \App\MyClasses\L2ModelWeb $l2 */ @endphp

<div class="l2karn-card_container">
  <div class="l2-card_wishlist l2heart" data-model-id="{{ $l2->getModelId() }}"
    data-model-name="{{ strip_tags($l2->getNameNoBr(29)) }}" data-model-pic="{{ $l2->getPicUrl(request()->lang) }}"
    data-model-url="{{ $l2->getL3Url(request()->lang) }}">
    <i class="fas fa-heart"></i>
  </div>
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