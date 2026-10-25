@php
    /** @var \App\MyClasses\Header $header */
    $header = new \App\MyClasses\Header(request()->lang);
@endphp

<style>
    .request-modal-model { font-weight: 400; }
    .request-modal-date-label { display: block; margin-bottom: 5px; font-size: 16px; color: #275991; }
    .request-modal-date-label span { color: #999; font-size: 14px; }
    /* iOS Safari: пустой date-инпут без appearance:none схлопывается, значение центрируется */
    .request-modal-date { -webkit-appearance: none; appearance: none; display: block; padding-right: 15px; }
    .request-modal-date::-webkit-date-and-time-value { text-align: left; }
</style>

<!-- Product Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestModalLabel">Оставить заявку<span id="requestModalModelName" class="request-modal-model"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="/zvonok" class="back-coll-modal">
                    @csrf
                    <input type="hidden" name="model_id" id="requestModalModelId" value="">
                    <div class="input-wrapper">
                        <input data-targetinput="" class="call-input1" type="text" name="fio"
                            placeholder="{{$header->translate('Ваше имя')}}" style="width: 100%; margin-bottom: 15px;"
                            required>
                    </div>
                    <div class="input-wrapper">
                        <input data-targetinput="" class="call-input1" type="text" name="phone"
                            placeholder="{{$header->translate('Телефон')}}" style="width: 100%; margin-bottom: 15px;"
                            required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="requestModalNeedDate" class="request-modal-date-label">С какой даты нужен товар <span>(необязательно)</span></label>
                        <input id="requestModalNeedDate" class="call-input1 request-modal-date" type="date" name="need_date">
                    </div>
                    <div class="input-wrapper">
                        <textarea id="requestModalInfo" data-targetinput="" class="call-textarea1" name="info"
                            placeholder="Комментарий (необязательно)"
                            style="width: 100%; margin-bottom: 15px; min-height: 80px;"></textarea>
                    </div>
                    <button type="submit"
                        style="width: 100%; padding: 10px; border-radius: 25px; background: #fff; border: 1px solid #275991; color: #275991;">{{$header->translate('Отправить')}}</button>
                    <div class="text-center mt-2" style="font-size: 12px; color: #666;">
                        Мы свяжемся с вами в ближайшее время, чтобы уточнить наличие.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var requestModal = document.getElementById('requestModal');
        if (requestModal) {
            requestModal.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal
                var button = event.relatedTarget;
                // Extract info from data-bs-* attributes
                var modelName = button.getAttribute('data-model-name');
                var modelId = button.getAttribute('data-model-id');

                // Update the modal's content.
                // Название товара — в заголовке, а не в комментарии: в CRM модель и так видна по model_id
                var modelIdInput = requestModal.querySelector('#requestModalModelId');
                requestModal.querySelector('#requestModalModelName').textContent = modelName ? ' — ' + modelName : '';
                requestModal.querySelector('#requestModalInfo').value = '';

                // Дата: от сегодня до года вперёд (те же границы проверяет ZvonokController)
                var dateInput = requestModal.querySelector('#requestModalNeedDate');
                var ymd = function (d) {
                    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
                };
                var today = new Date();
                dateInput.value = '';
                dateInput.min = ymd(today);
                dateInput.max = ymd(new Date(today.getFullYear() + 1, today.getMonth(), today.getDate()));

                if (modelIdInput) {
                    modelIdInput.value = modelId || '';
                }
            });
        }
    });
</script>