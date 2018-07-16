@if (isset($alert))
    <article class="message {{ object_get($alert, 'class', 'is-primary') }} dismissable-wrapper">
      <div class="message-header">
        <p>{{ object_get($alert, 'title', 'Notice') }}</p>
        <a href="javascript:void()" class="delete js-dismissable"></a>
      </div>
      <div class="message-body">
        {!! $alert->alert !!}
      </div>
    </article>
@endif
