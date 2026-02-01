@php /** @var \App\MyClasses\L2ModelWeb $l2 */ @endphp

<div class="l2-card_container">
  <div class="l2-card_top-section">
    <div class="d-flex justify-content-between align-items-start">
      <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_header-link">
        <span class="l2-card_header">{!! $l2->getName() !!}</span>
      </a>
      <div class="l2-card_wishlist">
        <i class="fas fa-heart"></i>
      </div>
    </div>

    <div class="l2-card_estimated-cost">
      Оценочная стоимость: {{ number_format($l2->getEstimatedValue(), 0, ',', ' ') }} BYN
    </div>

    <div class="l2-card_rating">
      <i class="fas fa-star"></i>
      <i class="fas fa-star"></i>
      <i class="fas fa-star"></i>
      <i class="fas fa-star-half-alt"></i>
      <i class="far fa-star"></i>
    </div>
  </div>

  <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_img-container">
    <img src="{{ $l2->getPicUrl(request()->lang) }}" class="l2-card_img" alt="{{$l2->getPicAltText()}}">
    <!-- Plus icon logic if needed visually, implied by screenshot but maybe part of image? Adding overlay just in case -->
    <!-- <div class="l2-card_img-overlay">+</div> -->
  </a>

  <div class="l2-card_price-label">
    Тариф: <span class="price-from">от {{number_format($l2->getMinDayTarifValue(), 2, ',', ' ')}} BYN</span> за сутки
  </div>

  <!-- Pricing Bar Section -->
  <div class="l2-card_pricing-bar">
    <div class="l2-card_point-labels">
      <span>1 неделя</span>
      <span>2 недели</span>
      <span>3 недели</span>
      <span>4 недели</span>
    </div>

    <div class="l2-card_pricing-track">
      <div class="l2-card_node node-1"></div>
      <div class="l2-card_node node-2"></div>
      <div class="l2-card_node node-3"></div>
      <div class="l2-card_node node-4"></div>
    </div>

    <div class="l2-card_point-prices">
      <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(7), 0, ',', ' ') }} <small>BYN</small></span>
      <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(14), 0, ',', ' ') }}
        <small>BYN</small></span>
      <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(21), 0, ',', ' ') }}
        <small>BYN</small></span>
      <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(30), 0, ',', ' ') }}
        <small>BYN</small></span>
    </div>
  </div>

  <!-- Button -->
  <div class="l2-card_action-btn-container">
    @if($l2->isL2AvailabilityVisible() && $l2->hasItemsAvailable())
      <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_btn btn-rent">
        ВЗЯТЬ НАПРОКАТ
      </a>
    @else
      <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_btn btn-book">
        ЗАБРОНИРОВАТЬ
      </a>
    @endif
  </div>

</div>