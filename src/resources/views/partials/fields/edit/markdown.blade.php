@extends('laramie::partials.fields.edit._base')

@section('input')
    <div class="columns">
        <div class="column is-half markdown-textarea">
            <textarea class="textarea markdown" rows="12" id="{{ $field->id }}" name="{{ $field->id }}" {{ $field->extra }} {{ $field->required ? 'required' : '' }}>{{ object_get($item, $field->id . '.markdown') }}</textarea>
        </div>
        <div class="column is-half is-hidden-mobile markdown-preview">
            <div class="content markdown-html">{!! object_get($item, $field->id . '.html') !!}</div>
        </div>
    </div>
@overwrite
