@extends('laramie::partials.fields.edit._base')

@section('input')
    <div class="columns">
        <div class="column is-12-desktop is-half-fullhd markdown-textarea">
            <textarea class="textarea markdown" rows="12" id="{{ $field->id }}" name="{{ $field->id }}" {!! $field->extra !!} {{ $field->required ? 'required' : '' }}>{{ object_get($item, $field->id . '.markdown') }}</textarea>
            <p class="is-hidden-fullhd markdown-preview-link" style="margin-top: .5rem;"><a class="tag js-preview-markdown" href="javascript:void(0);">Preview</a></p>
        </div>
        <div class="column is-half is-hidden-touch is-hidden-desktop-only is-hidden-widescreen-only markdown-preview">
            <div class="content markdown-html">{!! object_get($item, $field->id . '.html') !!}</div>
        </div>
    </div>
@overwrite
