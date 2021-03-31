@extends('laramie::partials.fields.edit._base')

@php
    $fileInfo = data_get($item, $field->getId(), (object) []);
    $uploadKey = data_get($fileInfo, 'uploadKey');
    $uploadType = $field->getSubtype() ?: $field->getType();
@endphp

@section('input')
    <div class="reference-wrapper file-wrapper{{ $uploadKey ? ' has-file is-checked' : '' }}">
        <label class="hide-when-no-file top-level-label">
            <input type="checkbox" class="reference-ids" name="_{{ $field->getId() }}" {!! $uploadKey ? 'checked="checked"' : '' !!} onchange="$(this).closest('.reference-wrapper').toggleClass('is-checked', $(this).is(':checked'));" value="{{ $uploadKey }}"> Use <span class="selection-info" data-base-url="javascript:void(0);"><a class="js-file-name" onclick="dynamicFileHref(this);" href="javascript:void(0);" target="_blank"><img class="filetype-icon" src="{{ config('laramie.admin_url') }}/assets/icon/{{ data_get($fileInfo, 'uploadKey') }}_50">{{ data_get($fileInfo, 'name') }}</a></span>
        </label>
        <div class="hide-when-file">
            <div class="columns is-vcentered">
                <div class="column is-half">
                    <input type="file" class="input is-{{ $field->getType() }}" id="{{ $field->getId() }}" name="{{ $field->getId() }}" {!! $field->getExtra() !!} {!! $uploadType === 'image' ? 'accept="image/*"' : '' !!} {{ $field->isRequired() ? 'required' : '' }}>
                </div>
                @if ($field->canChooseFromLibrary())
                    <div class="column is-half">
                        <a class="tag js-toggle-reference-search">Choose from library</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="modal reference-search" data-field="{{ $field->getFieldName() }}" data-type="{{ $model->getType() }}" data-lookup-type="laramieUpload" data-lookup-subtype="{{ $uploadType }}" data-is-single-reference="1">
            <div class="modal-background"></div>
            <div class="modal-card">
                <header class="modal-card-head">
                    <p class="modal-card-title">{{ \Str::plural(\Str::title($uploadType)) }}</p>
                    <span class="delete js-close-modal"></span>
                </header>
                <section class="modal-card-body">
                    <div class="search has-margin-bottom">
                        <p class="control has-icons-left">
                            <input class="keywords input" type="text" placeholder="Search filename / tag">
                            <span class="icon is-small is-left"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg></i></span>
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

