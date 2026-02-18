@php
    /** @var \App\MyClasses\Header $header */
    $header = new \App\MyClasses\Header(request()->lang);
@endphp

<!-- Product Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestModalLabel">Оставить заявку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="/zvonok" class="back-coll-modal">
                    @csrf
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
                    <div class="input-wrapper">
                        <textarea id="requestModalInfo" data-targetinput="" class="call-textarea1" name="info"
                            placeholder="{{$header->translate('Дополнительная информация')}}"
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

                // Update the modal's content.
                var modalBodyInput = requestModal.querySelector('#requestModalInfo');

                if (modelName) {
                    modalBodyInput.value = 'Интересует товар: ' + modelName;
                } else {
                    modalBodyInput.value = '';
                }
            });
        }
    });
</script>