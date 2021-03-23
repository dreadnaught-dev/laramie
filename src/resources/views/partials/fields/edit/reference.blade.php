@extends('laramie::partials.fields.edit._base')

@php
    $referencedModelKey = $field->getRelatedModel();
    $referencedModelNamePlural = $field->getLabelPlural();
    $isSingleReference = $field->getSubtype() === 'single';

    $references = data_get($item, $field->getId(), []);
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
            <input type="hidden" class="reference-ids" name="{{ $field->getId() }}" value="{{ $hasReferences ? $references->map(function($e){ return $e->id; })->implode('|') : '' }}">
            <blockquote>
                <div class="selection-info is-pulled-left" data-base-url="{{ route('laramie::edit', ['modelKey' => $referencedModelKey, 'id' => 'new'])  }}">
                    @forelse ($references as $reference)
                        <em><a href="{{ route('laramie::edit', ['modelKey' => $referencedModelKey, 'id' => $reference->id, 'is-child' => 1]) }}" target="_blank">{{ data_get($reference, '_alias') }}</a></em>{{ $loop->last ? '' : ', ' }}
                    @empty
                        Nothing selected
                    @endforelse
                </div>
                &nbsp;&nbsp;<a class="tag is-dark js-toggle-reference-search">Change</a>
            </blockquote>
        </div>
        <div class="modal reference-search" data-type="{{ $model->getType() }}" data-lookup-type="{{ $referencedModelKey }}" data-is-single-reference="{{ $isSingleReference ? 1 : 0 }}">
            <div class="modal-background"></div>
            <div class="modal-card">
                <header class="modal-card-head">
                    <p class="modal-card-title">{{ $referencedModelNamePlural }}</p>
                    <span class="delete js-close-modal"></span>
                </header>
                <section class="modal-card-body">
                    <div class="search has-margin-bottom">
                        <p class="control has-icons-left">
                            <input class="keywords input" type="text" placeholder="Search">
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
                            <a class="level-item button is-primary is-loading js-select-reference">Apply</a>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>
@overwrite
