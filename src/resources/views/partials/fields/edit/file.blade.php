@extends('laramie::partials.fields.edit._base')

@php
    $fileInfo = object_get($item, $field->id, (object) []);
    $uploadKey = object_get($fileInfo, 'uploadKey');
    $uploadType = object_get($field, 'subtype', object_get($field, 'type'));
@endphp

@section('input')
    <div class="reference-wrapper file-wrapper{{ $uploadKey ? ' has-file' : '' }}">
        <label class="hide-when-no-file top-level-label">
            <input type="checkbox" class="reference-ids" name="_{{ $field->id }}" {!! $uploadKey ? 'checked="checked"' : '' !!} onchange="$(this).parent().next().toggle(!$(this).is(':checked'));" value="{{ $uploadKey }}"> Use <span class="selection-info" data-base-url="javascript:void(0);"><a class="js-file-name" onclick="dynamicFileHref(this);" href="javascript:void(0);" target="_blank"><img class="filetype-icon" src="/admin/assets/icon/{{ object_get($fileInfo, 'uploadKey') }}_50">{{ object_get($fileInfo, 'name') }}</a></span>
        </label>
        <div class="hide-when-file">
            <div class="columns is-vcentered">
                <div class="column is-half">
                    <input type="file" class="input is-{{ $field->type }}" id="{{ $field->id }}" name="{{ $field->id }}" {{ $field->extra }} {{ $field->required ? 'required' : '' }}>
                </div>
                @if (object_get($field, 'canChooseFromLibrary') !== false)
                    <div class="column is-half">
                        <a class="tag js-toggle-reference-search">Choose from library</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="columns reference-search hide-when-file" data-field="{{ data_get($field, '_fieldName') }}" data-type="{{ $model->_type }}" data-lookup-type="laramieUpload" data-lookup-subtype="{{ $uploadType }}" data-is-single-reference="1" style="display:none">
            <div class="column is-half">
                <nav class="panel">
                    <div class="panel-heading">
                        {{ str_plural(title_case($uploadType)) }}
                    </div>
                    <div class="panel-block search">
                        <p class="control has-icon clearfix">
                            <input class="keywords input" type="text" placeholder="Search filename / tag">
                            <span class="icon is-small"><i class="fas fa-search"></i></span>
                        </p>
                    </div>
                    <div class="panel-block option">
                        Loading...
                    </div>
                    <div class="panel-block">
                        <a class="button is-primary is-loading js-select-reference is-file-select">Select</a>
                        &nbsp;
                        <a class="button is-light js-cancel js-toggle-reference-search">Cancel</a>
                    </div>
                </nav>
            </div>
        </div>
    </div>
@overwrite

