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
        <div class="l2-card_wishlist" data-model-id="{{ $l2->getModelId() }}"
          data-model-name="{{ strip_tags($l2->getName()) }}" data-model-pic="{{ $l2->getPicUrl() }}"
          data-model-url="{{ $l2->getL3Url(request()->lang) }}">
          <i class="fas fa-heart"></i>
        </div>
      </div>

      <div class="l2-card_estimated-cost">
        Купить в магазинах: ~{{ number_format($l2->getEstimatedValue(), 0, ',', ' ') }} BYN
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
      @php
        $basePeriod = $l2->getTarifLinePeriodDaysNumber();
      @endphp

      <div class="l2-card_point-labels">
        @if($basePeriod == 7)
          {{-- Weekly tariff --}}
          <span>1 неделя</span>
          <span>2 недели</span>
          <span>3 недели</span>
          <span>4 недели</span>
        @elseif($basePeriod == 30)
          {{-- Monthly tariff --}}
          <span>1 месяц</span>
          <span>2 месяца</span>
          <span>3 месяца</span>
          <span>4 месяца</span>
        @else
          {{-- Daily tariff (fallback) --}}
          <span>1 сутки</span>
          <span>2 суток</span>
          <span>3 суток</span>
          <span>4 суток</span>
        @endif
      </div>

      <div class="l2-card_point-prices">
        @if($basePeriod == 7)
          {{-- Weekly prices --}}
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(7), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(14), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(21), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(28), 0, ',', ' ') }}<small>BYN</small></span>
        @elseif($basePeriod == 30)
          {{-- Monthly prices --}}
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(30), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(60), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(90), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(120), 0, ',', ' ') }}<small>BYN</small></span>
        @else
          {{-- Daily prices (fallback) --}}
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(1), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(2), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(3), 0, ',', ' ') }}<small>BYN</small></span>
          <span>{{ number_format($l2->getTarifModel()->getAmmountForDaysPeriod(4), 0, ',', ' ') }}<small>BYN</small></span>
        @endif
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
        @if($basePeriod == 30)
          <span>При аренде от 4-х мес</span>
          <span class="tariff-price">{{ $integerPart }}<span class="tariff-comma">,</span><sup
              class="tariff-decimal">{{ $decimalPart }}</sup> BYN/сутки</span>
        @else
          <span>При аренде от 28 дн.</span>
          <span class="tariff-price">{{ $integerPart }}<span class="tariff-comma">,</span><sup
              class="tariff-decimal">{{ $decimalPart }}</sup> BYN/сутки</span>
        @endif
      </div>
    </div>

    <div class="l2-card_meta-info">
      @php
        $availability = $l2->getAvailabilityInfo();
      @endphp

      {{-- Line 1: Header --}}
      @if($availability['hasAvailability'])
        <div class="meta-row meta-row-header">
          <span class="meta-header-text">
            {{ count($availability['offices']) > 1 ? 'Товар в наличии по адресам:' : 'Товар в наличии по адресу:' }}
          </span>
        </div>

        {{-- Line 2: Addresses --}}
        <div class="meta-row meta-row-addresses">
          @php
            $sortedOffices = collect($availability['offices'])->sortBy(function ($office) {
              // Return 0 for Literaturnaya (first), 1 for others
              return (mb_stripos($office->getAddressShort(), 'Литературная') !== false) ? 0 : 1;
            });
          @endphp
          @foreach($sortedOffices as $index => $office)
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
          @endforeach
        </div>

        {{-- Line 3: Delivery & Details (Merged) --}}
        <div class="meta-row meta-row-delivery">
          @php
            $now = \Carbon\Carbon::now('Europe/Minsk');
            $isWeekend = $now->isWeekend();
            $cutoff = $isWeekend ? 13 : 17;
            $deliveryText = $now->hour < $cutoff ? 'сегодня' : 'завтра';
          @endphp
          <div class="delivery-info">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4F82D7"
              stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="delivery-icon">
              <path d="M10 17l5-5-5-5"></path>
              <path d="M13.8 12H3"></path>
              <path d="M20 4v16"></path>
            </svg>
            <span class="meta-delivery-bold">Доставка:</span> <span>{{ $deliveryText }}</span>
          </div>
          <a href="https://tiktak.by/ru/delivery" class="meta-delivery-link">Подробнее...</a>
        </div>

      @else
        {{-- Show expected return date --}}
        @php
          $returnDate = \bb\classes\tovar::getEarliestReturnDateForModelId($l2->getModelId());
        @endphp
        @if($returnDate)
          @php
            $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
            $day = $returnDate->format('j');
            $monthIndex = (int) $returnDate->format('n') - 1;
            $expectedDate = 'Ожидается возврат ' . $day . ' ' . $months[$monthIndex];
          @endphp
          <div class="meta-row meta-row-date">
            <span class="meta-date-text">{{ $expectedDate }}</span>
          </div>
        @endif

        <div class="meta-row meta-row-request">
          <span class="meta-request-text">Оставьте заявку - мы сообщим о наличии!</span>
        </div>
      @endif
    </div>

    <div class="l2-card_action-btn-container">
      @if($availability['hasAvailability'])
        @php
          $cartTariffs = [];
          $tarifModel = $l2->getTarifModel();
          if ($tarifModel) {
            foreach ($tarifModel->getTarifs() as $t) {
              $daysNum = $t->getDaysCalculatedNumber();
              if ($daysNum > 0) {
                $dailyRate = round($t->getTotalAmount() / $daysNum, 2);
                $cartTariffs[] = [$daysNum, $dailyRate];
              }
            }
            usort($cartTariffs, function ($a, $b) {
              return $a[0] - $b[0];
            });
          }
        @endphp


        {{-- Add to Cart button --}}
        @php
          $cartTariffs = [];
          $tarifModel = $l2->getTarifModel();
          if ($tarifModel) {
            foreach ($tarifModel->getTarifs() as $t) {
              $daysNum = $t->getDaysCalculatedNumber();
              if ($daysNum > 0) {
                $dailyRate = round($t->getTotalAmount() / $daysNum, 2);
                $cartTariffs[] = [$daysNum, $dailyRate];
              }
            }
            usort($cartTariffs, function ($a, $b) {
              return $a[0] - $b[0];
            });
          }
        @endphp
        <button type="button" class="l2-card_btn-cart" data-model-id="{{ $l2->getModelId() }}"
          data-model-name="{{ strip_tags($l2->getName()) }}" data-model-pic="{{ $l2->getPicUrl() }}"
          data-model-url="{{ $l2->getL3Url(request()->lang) }}" data-tariffs='@json($cartTariffs)' onclick="TiktakCart.addItem({
                    modelId: {{ $l2->getModelId() }},
                    name: this.getAttribute('data-model-name'),
                    picUrl: this.getAttribute('data-model-pic'),
                    l3Url: this.getAttribute('data-model-url'),
                    dateFrom: TiktakCart.todayStr(),
                    days: 14,
                    tariffs: JSON.parse(this.getAttribute('data-tariffs'))
                  })">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            class="btn-icon">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
          В КОРЗИНУ
        </button>
      @else
        <a href="{{$l2->getL3Url(request()->lang)}}" class="l2-card_btn btn-request">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="btn-icon">
            <path
              d="M22 6C22 4.9 21.1 4 20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6ZM20 6L12 11L4 6H20ZM20 18H4V8L12 13L20 8V18Z"
              fill="currentColor" />
          </svg>
          ОСТАВИТЬ ЗАЯВКУ
        </a>
      @endif
    </div>
  </div>
@endif