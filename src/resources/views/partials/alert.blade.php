@php
    $alerts = collect([$systemMessage, $alert])->filter();
@endphp

@foreach ($alerts as $alert)
    <article class="message {{ data_get($alert, 'class', 'is-primary') }} dismissable-wrapper">
        <div class="message-header">
            <p>{{ data_get($alert, 'title', 'Notice') }}</p>
            <a href="javascript:void()" class="delete js-dismissable"></a>
        </div>
        <div class="message-body">
            {!! $alert->alert !!}
        </div>
    </article>
@endforeach
