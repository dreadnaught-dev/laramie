@extends('laramie::partials.fields.edit._base')

@section('input')
    <?php /*
        Note that we're injecting `$metaId` into the id of the field (but not
        the `name`). So that we don't run id collision issues with the trix
        editor (e.g., with side-by-side panel editing).
    */ ?>
    <input type="hidden" id="{{ $metaId . $field->id }}" name="{{ $field->id }}" value="{{ data_get($item, $field->id) }}">
    <trix-editor input="{{ $metaId . $field->id }}" class="trix-content"></trix-editor>
@overwrite
