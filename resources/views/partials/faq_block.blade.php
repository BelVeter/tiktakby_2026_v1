@if(isset($faq) && is_array($faq) && count($faq) > 0)
<div class="faq-section mt-5 mb-5">
    <div class="container-app">
        <h2 class="text-center mb-4" style="color: #3180d1; font-weight: bold;">Часто задаваемые вопросы</h2>
        <div class="faq-container">
            @foreach($faq as $item)
                @if(isset($item['question']) && isset($item['answer']) && trim($item['question']) !== '' && trim($item['answer']) !== '')
                    <details class="faq-item mb-3" style="border: 1px solid #e4f0fe; border-radius: 8px; overflow: hidden; background-color: #fff;">
                        <summary class="faq-question p-3" style="cursor: pointer; background-color: #e4f0fe; color: #3180d1; font-weight: bold; list-style-type: none; position: relative;">
                            <span class="faq-icon" style="display: inline-block; width: 24px; text-align: center; margin-right: 10px; color: #477ecb;">+</span>
                            {{ $item['question'] }}
                        </summary>
                        <div class="faq-answer p-3" style="border-top: 1px solid #e4f0fe; color: #333;">
                            <div>
                                {!! nl2br(strip_tags($item['answer'], '<a><b><strong><i><em><ul><li><ol><p><br><h1><h2><h3><h4><h5><h6>')) !!}
                            </div>
                        </div>
                    </details>
                @endif
            @endforeach
        </div>
    </div>
</div>

<style>
    .faq-item summary::-webkit-details-marker {
        display: none;
    }
    .faq-item[open] summary .faq-icon {
        transform: rotate(45deg);
        transition: transform 0.2s ease-in-out;
    }
    .faq-item:not([open]) summary .faq-icon {
        transform: rotate(0deg);
        transition: transform 0.2s ease-in-out;
    }
    .faq-item summary {
        transition: background-color 0.2s ease;
    }
    .faq-item summary:hover {
        background-color: #d0e4fd !important;
    }
</style>
@endif
