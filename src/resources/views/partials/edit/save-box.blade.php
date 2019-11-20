<div class="card save-box">
    <header class="card-header">
        <p class="card-header-title">Save</p>
    </header>
    <div class="card-content">
        <div class="content">
            @if ($item->_isNew)
                This is a <em><strong>new</strong></em> item and hasn't been saved yet.
            @else
                Last updated {{ \Carbon\Carbon::parse($item->updated_at, config('laramie.timezone'))->toDayDateTimeString() }}
                @if (object_get($lastUserToUpdate, 'id'))
                    by
                    @if ($user->isSuperAdmin || $user->isAdmin)
                        <a href="{{ route('laramie::edit', ['modelKey' => 'laramieUser', 'id' => $lastUserToUpdate->id]) }}">{{ $lastUserToUpdate->user }}</a>
                    @else
                        {{ $lastUserToUpdate->user }}
                    @endif
                @endif
            @endif
        </div>
    </div>
    <footer class="card-footer">
        <div class="card-footer-item">
            <div class="field is-grouped" style="width:100%">
                <p class="control is-expanded">
                    <a href="javascript:void(0);" class="button is-primary js-save is-fullwidth">Save{{ $item->_isUpdate ? ' changes' : ''}}</a>
                </p>
                <p class="control {{ $item->_isNew ? 'is-expanded' : '' }}">
                    <a href="{{ route('laramie::go-back', ['modelKey' => $model->_type]) }}" class="button is-light js-cancel-edit">Cancel</a>
                </p>
                @if ($item->_isUpdate)
                    <p class="control">
                        <a href="javascript:void(0);" class="button is-text has-text-danger js-delete">Delete</a>
                    </p>
                @endif
            </div>
        </div>
    </footer>
</div>

