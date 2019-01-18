<script id="reference-single-option" type="text/x-handlebars-template">
    @{{#each data}}
        <label class="panel-block option"><div class="columns is-gapless" style="width:100%"><div class="column is-1"><input type="radio" name="@{{ name }}" value="@{{ id }}" data-label="@{{ label }}" @{{#if selected}}checked@{{/if}}></div><div class="column is-11">@{{{ label }}}<br><em class="is-small" style="color: #ddd;">(@{{ id }})</em></div></div></label>
    @{{else}}
        <div class="panel-block option">No results found</div>
    @{{/each}}
</script>

<script id="reference-many-option" type="text/x-handlebars-template">
    @{{#each data}}
        <label class="panel-block option"><div class="columns is-gapless" style="width:100%"><div class="column is-1"><input type="checkbox" value="@{{ id }}" data-label="@{{ label }}" @{{#if selected}}checked@{{/if}}></div><div class="column is-11">@{{{ label }}}<br><em class="is-small" style="color: #ddd;">(@{{ id }})</em></div></div></label>
    @{{else}}
        <div class="panel-block option">No results found</div>
    @{{/each}}
</script>

