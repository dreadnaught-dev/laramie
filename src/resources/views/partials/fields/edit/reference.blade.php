@extends('laramie::partials.fields.edit._base')

@php
    $referencedModelKey = $field->relatedModel;
    $referencedModelNamePlural = $field->labelPlural;
    $isSingleReference = $field->subtype == 'single';

    $references = object_get($item, $field->id, []);
    if ($isSingleReference) {
        $references = [$references];
    }
    $references = collect($references)
        ->filter();

    $hasReferences = count($references) > 0;
@endphp

@section('input')
    <div class="reference-wrapper">
        <div class="content">
            <input type="hidden" class="reference-ids" name="{{ $field->id }}" value="{{ $hasReferences ? $references->map(function($e){ return $e->id; })->implode('|') : '' }}">
            <blockquote>
                <div class="selection-info is-pulled-left" data-base-url="{{ route('laramie::edit', ['modelKey' => $referencedModelKey, 'id' => 'new'])  }}">
                    @forelse ($references as $reference)
                        <em><a href="{{ route('laramie::edit', ['modelKey' => $referencedModelKey, 'id' => $reference->id]) }}" target="_blank">{{ object_get($reference, '_alias') }}</a></em>{{ $loop->last ? '' : ', ' }}
                    @empty
                        Nothing selected
                    @endforelse
                </div>
                &nbsp;&nbsp;<a class="tag is-dark js-toggle-reference-search">Change</a>
            </blockquote>
        </div>
        <div class="columns reference-search" data-type="{{ $model->_type }}" data-lookup-type="{{ $referencedModelKey }}" data-is-single-reference="{{ $isSingleReference ? 1 : 0 }}" style="display:none;">
            <div class="column is-half">
                <nav class="panel">
                    <p class="panel-heading">
                        {{ $referencedModelNamePlural }}
                    </p>
                    <div class="panel-block search">
                        <p class="control has-icon">
                            <input class="keywords input" type="text" placeholder="Search">
                            <span class="icon is-small"><i class="fas fa-search"></i></span>
                        </p>
                    </div>
                    <div class="panel-block option">
                        Loading...
                    </div>
                    <div class="panel-block">
                        <a class="button is-primary is-loading js-select-reference">Select</a>
                        &nbsp;
                        <a class="button is-warning js-clear-reference-select">Clear selection</a>
                        &nbsp;
                        <a class="button is-light js-cancel js-toggle-reference-search">Cancel</a>
                    </div>
                </nav>
            </div>
        </div>
    </div>
@overwrite
