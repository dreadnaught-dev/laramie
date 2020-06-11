@extends('laramie::partials.fields.edit._base')

@php
    $fileInfo = data_get($item, $field->id, (object) []);
    $uploadKey = data_get($fileInfo, 'uploadKey');
    $uploadType = data_get($field, 'subtype', object_get($field, 'type'));
@endphp

@section('input')
    <div class="reference-wrapper file-wrapper{{ $uploadKey ? ' has-file is-checked' : '' }}">
        <label class="hide-when-no-file top-level-label">
            <input type="checkbox" class="reference-ids" name="_{{ $field->id }}" {!! $uploadKey ? 'checked="checked"' : '' !!} onchange="$(this).closest('.reference-wrapper').toggleClass('is-checked', $(this).is(':checked'));" value="{{ $uploadKey }}"> Use <span class="selection-info" data-base-url="javascript:void(0);"><a class="js-file-name" onclick="dynamicFileHref(this);" href="javascript:void(0);" target="_blank"><img class="filetype-icon" src="{{ config('laramie.admin_url') }}/assets/icon/{{ data_get($fileInfo, 'uploadKey') }}_50">{{ object_get($fileInfo, 'name') }}</a></span>
        </label>
        <div class="hide-when-file">
            <div class="columns is-vcentered">
                <div class="column is-half">
                    <input type="file" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" {!! $field->extra !!} {!! $uploadType === 'image' ? 'accept="image/*"' : '' !!} {{ $field->required ? 'required' : '' }}>
                </div>
                @if (data_get($field, 'canChooseFromLibrary') !== false)
                    <div class="column is-half">
                        <a class="tag js-toggle-reference-search">Choose from library</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="modal reference-search" data-field="{{ data_get($field, '_fieldName') }}" data-type="{{ $model->_type }}" data-lookup-type="laramieUpload" data-lookup-subtype="{{ $uploadType }}" data-is-single-reference="1">
            <div class="modal-background"></div>
            <div class="modal-card">
                <header class="modal-card-head">
                    <p class="modal-card-title">{{ str_plural(title_case($uploadType)) }}</p>
                    <span class="delete js-close-modal"></span>
                </header>
                <section class="modal-card-body">
                    <div class="search has-margin-bottom">
                        <p class="control has-icons-left">
                            <input class="keywords input" type="text" placeholder="Search filename / tag">
                            <span class="icon is-small is-left"><i class="fas fa-search"></i></span>
                        </p>
                    </div>
                    <table class="table is-hoverable is-fullwidth">
                        <tbody class="results">
                            <tr><td colspan="2">Loading...</td></tr>
                        </tbody>
                    </table>
                </section>
                <footer class="modal-card-foot">
                    <div class="level" style="width: 100%;">
                        <div class="level-left">
                            <a class="level-item button is-warning js-clear-reference-select">Clear selection</a>
                        </div>
                        <div class="level-right">
                            <a class="level-item button is-light js-cancel js-toggle-reference-search">Cancel</a>
                            <a class="level-item button is-primary is-loading js-select-reference is-file-select">Apply</a>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>
@overwrite

