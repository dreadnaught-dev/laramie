@php
    $canSave = ($item->isNew() && $user->hasAccessToLaramieModel($model->getType(), 'create')) ||
        ($item->isUpdating() && $user->hasAccessToLaramieModel($model->getType(), 'update'));
@endphp

<div class="card save-box">
    <header class="card-header">
        <p class="card-header-title">{{ $canSave ? 'Save' : 'Info' }}</p>
    </header>
    <div class="card-content">
        <div class="content">
            @if ($item->isNew())
                This is a <em><strong>new</strong></em> item and hasn't been saved yet.
            @else
                Last updated {{ \Carbon\Carbon::parse($item->updated_at)->toDayDateTimeString() }}
                @if (data_get($lastUserToUpdate, 'id'))
                    by
                    {{ data_get($lastUserToUpdate, config('laramie.username', '--')) }}
                @endif
            @endif
        </div>
    </div>
    <footer class="card-footer">
        <div class="card-footer-item">
            <div class="field is-grouped" style="width:100%">
                @if ($canSave)
                <p class="control is-expanded">
                    <a href="javascript:void(0);" class="button is-primary js-save is-fullwidth">Save{{ $item->isNew() ? '' : ' changes' }}</a>
                </p>
                @endif
                <p class="control {{ $item->isNew() ? 'is-expanded' : '' }}">
                    <a href="{{ route('laramie::go-back', ['modelKey' => $model->getType()]) }}" class="button is-light js-cancel-edit">{{ $canSave ? 'Cancel' : 'Go Back' }}</a>
                </p>
                @if ($item->isUpdating() && $model->isDeletable() && $user->hasAccessToLaramieModel($model->getType(), 'delete'))
                    <p class="control">
                        <a href="javascript:void(0);" class="button is-text has-text-danger js-delete">Delete</a>
                    </p>
                @endif
            </div>
        </div>
    </footer>
</div>

