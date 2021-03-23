@extends('laramie::partials.fields.edit._base')

@section('input')
    <?php /*
        Note that we're injecting `$metaId` into the id of the field (but not
        the `name`). So that we don't run id collision issues with the trix
        editor (e.g., with side-by-side panel editing).
    */ ?>
    <input type="hidden" id="{{ $metaId . $field->getId() }}" name="{{ $field->getId() }}" value="{{ $valueOrDefault }}">
    <trix-editor input="{{ $metaId . $field->getId() }}" class="trix-content"></trix-editor>
@overwrite
