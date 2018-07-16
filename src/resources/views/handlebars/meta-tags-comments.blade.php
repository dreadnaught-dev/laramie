<script id="tag-list-template" type="text/x-handlebars-template">
    @{{#if tags.length}}
        <div class="field is-grouped is-grouped-multiline">
            @{{#each tags}}
            <div class="control">
                <div class="tags has-addons">
                    <span class="tag is-info is-medium">@{{text}}</span>
                    <a class="tag is-delete is-medium" data-id="@{{id}}"></a>
                </div>
            </div>
            @{{/each}}
        </div>
    @{{else}}
        <p>No tags to show.</p>
    @{{/if}}
</script>

<script id="comment-list-template" type="text/x-handlebars-template">
    @{{#each comments}}
        <article id="comment-@{{id}}" class="media margin-bottom is-small">
            <figure class="media-left">
                <span class="tag is-rounded is-medium" style="background-color: @{{_color}};">@{{_userFirstInitial}}</span>
            </figure>
            <div class="media-content">
                <div class="content">
                    <p class="is-size-7 is-marginless"><strong>@{{_user}}</strong> @{{lastModified}}</p>
                    @{{{html}}}
                </div>
            </div>
            <div class="media-right">
                <button class="delete is-delete is-comment-delete" data-id="@{{id}}"></button>
            </div>
        </article>
    @{{else}}
        <p>No comments to show.</p>
    @{{/each}}
</script>

