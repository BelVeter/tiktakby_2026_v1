@extends('layouts.empty')
@php /** @var \App\MyClasses\KBForm $f */ @endphp

@section('content')
  <div class="kbform_free">
    <div class="header_line">
      <div class="controll-div">
        <div class="btn-back"><img src="/public/svg/back_arrow_l3karn.svg" alt="close modal"></div>
        <div class="btn-close3" data-bs-dismiss="modal" aria-label="Close"><img src="/public/svg/l3_modal_cross.svg"
                                                                                alt="close modal"></div>
      </div>
      <table class="header-table">
        <tr>
          <td class="name" colspan="2">{!! $f->getTovarName() !!}</td>
        </tr>
        <tr>
          <td class="name">Размер</td>
          <td class="value">{{$f->getRostFrom()}}-{{$f->getRostTo()}}, {{$f->getSize()}}</td>
        </tr>
        <tr>
          <td class="name">Дата праздника</td>
          <td
            class="value">{{$f->getEventDate()->format('d')}} {{\bb\Base::getMonthNameForDay($f->getEventDate()->format('m'))}}</td>
        </tr>
      </table>
    </div>

    <p>Для формирования брони, укажите, пожалуйста, желаемый период аренды</p>
    @foreach($f->getFreePeriods() as $fp)
      <div class="free-period {{$loop->index == 0 ? 'selected' : ''}}" data-inv="{{$fp->inv_n}}" data-from="{{$fp->from_kb->format('Y-m-d H:00')}}" data-to="{{$fp->to_kb->format('Y-m-d H:00')}}">
        <!-- schedule -->
        @foreach(\bb\Schedule::getDateOpenCloseHoursArrayForPreiod($fp->from_kb, $fp->to_kb) as $ar)
          <input class="schedule" type="hidden" data-date="{{ $ar[0] }}" data-openHour="{{ $ar[1] }}" data-closeHour="{{ $ar[2] }}">
        @endforeach

        <div class="form-check">
          <input class="form-check-input free-period-radio" type="radio" name="freePeriod" id="freePeriod{{$loop->index}}" {{$loop->index == 0 ? 'checked' : ''}}>
          <label class="form-check-label" for="freePeriod{{$loop->index}}">
            свободно c {{$fp->from_kb->format("H")}}<sup>{{$fp->from_kb->format("i")}}</sup> {{$fp->from_kb->format("d.m")}}
            по {{$fp->to_kb->format("H")}}<sup>{{$fp->to_kb->format("i")}}</sup> {{$fp->to_kb->format("d.m")}}
          </label>
        </div>
        <div class="line1 time-line">
          <div class="time_text">Выдача костюма</div>
          <div>
            <select class="kb_select day" name="from_day" id="from_day">
              <option value="">дата</option>
              @foreach(\App\MyClasses\KBForm::getFromDaysArray($fp, $f->getEventDate()) as $d)
                <option value="{{$d->format('Y-m-d')}}">{{$d->format('d.m.Y')}}</option>
              @endforeach
            </select>
          </div>
          <div>
            <select class="kb_select time" name="from_time" id="from_time">
              <option value="">время</option>
            </select>
          </div>
        </div>
        <div class="line2 time-line">
          <div class="time_text">Возврат костюма</div>
          <div>
            <select class="kb_select day" name="to_day" id="to_day">
              <option value="">дата</option>
              @foreach(\App\MyClasses\KBForm::getToDaysArray($fp, $f->getEventDate()) as $d)
                <option value="{{$d->format('Y-m-d')}}">{{$d->format('d.m.Y')}}</option>
              @endforeach
            </select>
          </div>
          <div>
            <select class="kb_select time" name="to_time" id="to_time">
              <option value="">время</option>
            </select>
          </div>
        </div>
      </div>
    @endforeach
    <input class="input" type="text" name="fio" id="kb_fio" placeholder="ФИО">
    <input class="input" type="text" name="phone" id="kb_phone" placeholder="Телефон">
    <textarea class="input" name="info" id="kb_info" placeholder="Комментарий"></textarea>
    <div class="last_line">
      <button class="kb_bron" type="button">Забронировать</button>
      <img class="kb_message" src="/public/png/message.png">
    </div>
  </div>

@endsection
