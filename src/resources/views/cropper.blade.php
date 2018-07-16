@extends('laramie::layout')

@push('extra-header')
    <link href="/laramie/admin/css/cropper.min.css" rel="stylesheet">
    <style>
        #crop-image {
            max-width: 100%; /* This rule is very important, please do not ignore this! */
        }
    </style>
@endpush

@push('scripts')
    <script src="/laramie/admin/js/cropper.min.js"></script>
    <script>
        $(document).ready(function(){
            window.zoom = 1;
            $('#cropper-form').submit(function(){
                $(this).append('<input type="hidden" name="zoom" value="'+ window.zoom +'">');
                var cropperData = cropper.getData();
                for (var key in cropperData) {
                    $(this).append('<input type="hidden" name="'+ key +'" value="'+ cropperData[key] +'">');
                }
                return true;
            });

            $(".js-save").click(function() {
                $("#cropper-form").submit();
            });

            var image = document.getElementById('crop-image');
            var cropper = new Cropper(image, {
                autoCrop: false,
                viewMode: 2,
                crop: function(e) {
                    $('#selection-dimensions').text('(' + Math.floor(e.detail.width) + 'px X ' + Math.floor(e.detail.height) + 'px)');
                },
                zoom: function(e) {
                    window.zoom = e.detail.ratio;
                }
            });
            window.cropper = cropper;
            $(':input.image-control').change(function(){
                cropper[$(this).data('operation')]($(this).val());
            });
            $(':button.image-control').click(function(){
                cropper[$(this).data('operation')]($(this).data('amount'));
            });
        });

    </script>
@endpush

@section('content')
    <div class="column">
        <div class="columns">
            <div class="column">
                @include('laramie::partials.alert')
                <h1 class="title">Crop / resize image</h1>
                <form id="cropper-form" method="post" action="{{ route('laramie::cropper', ['imageKey' => $imageKey]) }}">
                    <input type="hidden" name="_token" value="{!! csrf_token() !!}">

                    <div class="field">
                        <label class="label" for="alt">Alt text</label>
                        <div class="control">
                            <input type="text" class="input is-text" id="alt" name="alt" value="{{ object_get($item, 'alt') }}">
                        </div>
                    </div>

                    <label class="label">Image</label>
                    <img id="crop-image" src="{{ route('laramie::image', ['imageKey' => $imageKey]) }}">
                </form>
            </div>
            <div class="column is-3">
                <div class="card">
                    <header class="card-header">
                        <p class="card-header-title">Image options</p>
                    </header>
                    <div class="card-content">
                        <div class="field">
                            <label class="label">Selection <small id="selection-dimensions"></small></label>
                            <p class="control">
                                <button class="button image-control" data-operation="clear" data-amount="">Clear</button>
                                <button class="button image-control" data-operation="crop" data-amount="">Default</button>
                            </p>
                        </div>
                        <div class="field">
                            <label class="label">Aspect ratio</label>
                            <p class="control">
                                <span class="select">
                                    <select class="image-control" data-operation="setAspectRatio">
                                        <option value="free">Free</option>
                                        <option value="{{ 16/9 }}">16:9</option>
                                        <option value="{{ 4/3 }}">4:3</option>
                                        <option value="1">1:1</option>
                                        <option value="{{ 2/3 }}">2:3</option>
                                    </select>
                                </span>
                            </p>
                        </div>
                        <div class="field">
                            <label class="label">Rotation</label>
                            <p class="control">
                                <button class="button image-control" data-operation="rotate" data-amount="-45"><span class="icon is-small"><i class="fas fa-undo"></i></span></button>
                                <button class="button image-control" data-operation="rotate" data-amount="45"><span class="icon is-small"><i class="fas fa-redo"></i></span></button>
                            </p>
                        </div>
                        <div class="field">
                            <label class="label">Flip</label>
                            <p class="control">
                                <button class="button image-control" data-operation="scaleX" data-amount="-1" onclick="$(this).data('amount', $(this).data('amount') == '-1' ? '1' : '-1');"><span class="icon is-small"><i class="fas fa-arrows-alt-h"></i></span></button>
                                <button class="button image-control" data-operation="scaleY" data-amount="-1" onclick="$(this).data('amount', $(this).data('amount') == '-1' ? '1' : '-1');"><span class="icon is-small"><i class="fas fa-arrows-alt-v"></i></span></button>
                            </p>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <a href="javascript:void(0);" class="card-footer-item js-save">Save</a>
                    </footer>
                </div>
            </div>
        </div>
    </div>
@endsection

