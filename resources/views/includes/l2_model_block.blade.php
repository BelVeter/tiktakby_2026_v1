@php /** @var \App\MyClasses\L2ModelWeb $l2 */ @endphp

@if($l2->isKarnaval())
  @include('includes.l2karn_model_block')

@else
  {{-- STANDARD LAYOUT (Redesign) --}}
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

      @php
        // Generate consistent random rating (4.5 or 5) based on product ID
        $rating = (($l2->getModelId() % 2) == 0) ? 5 : 4.5;
        $fullStars = floor($rating);
        $hasHalfStar = ($rating - $fullStars) >= 0.5;
      @endphp

      <div class="l2-card_rating">
        @for($i = 1; $i <= 5; $i++)
          @if($i <= $fullStars)
            <i class="fas fa-star"></i>
          @elseif($i == $fullStars + 1 && $hasHalfStar)
            <i class="fas fa-star-half-alt"></i>
          @else
            <i class="far fa-star"></i>
          @endif
        @endfor
      </div>
    </div>

    <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_img-container">
      <img src="{{ $l2->getPicUrl() }}" class="l2-card_img" alt="{{$l2->getPicAltText()}}">
    </a>

    <!-- New Pricing Section Layout -->
    <div class="l2-card_pricing-wrapper">
      <div class="l2-card_point-labels">
        <span>1 неделя</span>
        <span>2 недели</span>
        <span>3 недели</span>
        <span>4 недели</span>
      </div>

      <div class="l2-card_point-prices">
        <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(7), 0, ',', ' ') }}<small>BYN</small></span>
        <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(14), 0, ',', ' ') }}<small>BYN</small></span>
        <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(21), 0, ',', ' ') }}<small>BYN</small></span>
        <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(30), 0, ',', ' ') }}<small>BYN</small></span>
      </div>

      <div class="l2-card_pricing-track">
        <div class="track-line"></div>
      </div>

      <div class="l2-card_tariff-info">
        @php
          $minTariff = $l2->getMinDayTarifValue();
          $parts = explode('.', number_format($minTariff, 2, '.', ''));
          $integerPart = $parts[0];
          $decimalPart = $parts[1] ?? '00';
        @endphp
        При аренде от 28 дней -- тариф <span class="tariff-price">{{ $integerPart }}<span
            class="tariff-comma">,</span><sup class="tariff-decimal">{{ $decimalPart }}</sup> BYN/сутки</span>
      </div>
    </div>

    <div class="l2-card_meta-info">
      @php
        $availability = $l2->getAvailabilityInfo();
      @endphp

      <div class="meta-row">
        @if($availability['hasAvailability'])
          <span class="meta-label">{{ $availability['message'] }}</span>
          <div class="meta-value">
            @foreach($availability['offices'] as $index => $office)
              <span class="location-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path
                    d="M12 13.43C13.8943 13.43 15.43 11.8943 15.43 10C15.43 8.10571 13.8943 6.57 12 6.57C10.1057 6.57 8.57 8.10571 8.57 10C8.57 11.8943 10.1057 13.43 12 13.43Z"
                    stroke="#4CAF50" stroke-width="1.5" />
                  <path
                    d="M3.62 8.49C5.59 -0.169998 18.42 -0.159997 20.38 8.5C21.53 13.58 18.37 17.88 15.6 20.54C13.59 22.48 10.41 22.48 8.39 20.54C5.63 17.88 2.47 13.57 3.62 8.49Z"
                    stroke="#4CAF50" stroke-width="1.5" />
                </svg>
                {{ $office->getAddressShort() }}
              </span>
              @if($index < count($availability['offices']) - 1)
                <span class="separator">|</span>
              @endif
            @endforeach
          </div>
        @else
          <span class="meta-value">Товар ожидается</span>
        @endif
      </div>
      @if(!$availability['hasAvailability'])
        <div class="meta-row">
          <span class="meta-value">Оставьте заявку - мы перезвоним!</span>
        </div>
      @endif
      @if($availability['hasAvailability'])
        <div class="meta-row">
          <span class="meta-label">Доставка:</span>
          <div class="meta-value">
            возможна сегодня. <a href="#">Подробнее...</a>
          </div>
        </div>
      @endif
    </div>

    <div class="l2-card_action-btn-container">
      <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_btn btn-rent">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="btn-icon">
          <path
            d="M8 2V5M16 2V5M3.5 9.09H20.5M21 8.5V17C21 20 19.5 22 16 22H8C4.5 22 3 20 3 17V8.5C3 5.5 4.5 3.5 8 3.5H16C19.5 3.5 21 5.5 21 8.5Z"
            stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round"
            stroke-linejoin="round" />
          <path
            d="M15.6947 13.7H15.7037M15.6947 16.7H15.7037M11.9955 13.7H12.0045M11.9955 16.7H12.0045M8.29431 13.7H8.30329M8.29431 16.7H8.30329"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        ВЗЯТЬ НАПРОКАТ
      </a>
    </div>
  </div>
@endif