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
                            <input type="text" class="input is-text" id="alt" name="alt" value="{{ data_get($item, 'alt') }}">
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
                                <button class="button image-control" data-operation="rotate" data-amount="-45"><span class="icon is-small"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg></i></span></button>
                                <button class="button image-control" data-operation="rotate" data-amount="45"><span class="icon is-small"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg></i></span></button>
                            </p>
                        </div>
                        <div class="field">
                            <label class="label">Flip</label>
                            <p class="control">
                                <button class="button image-control" data-operation="scaleX" data-amount="-1" onclick="$(this).data('amount', $(this).data('amount') == '-1' ? '1' : '-1');"><span class="icon is-small"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0z" fill="none"/><path d="M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z"/></svg></i></span></button>
                                <button class="button image-control" data-operation="scaleY" data-amount="-1" onclick="$(this).data('amount', $(this).data('amount') == '-1' ? '1' : '-1');"><span class="icon is-small"><i class="g-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000"><path d="M0 0h24v24H0z" fill="none"/><path d="M16 17.01V10h-2v7.01h-3L15 21l4-3.99h-3zM9 3L5 6.99h3V14h2V6.99h3L9 3z"/></svg></i></span></button>
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

