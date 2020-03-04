<script id="reference-single-option" type="text/x-handlebars-template">
    @{{#each data}}
        <tr>
            <td style="width:1rem;">
                <input type="radio" id="@{{ name }}-@{{ id }}" name="@{{ name }}" value="@{{ id }}" data-label="@{{ label }}" @{{#if selected}}checked@{{/if}}>
            </td>
            <td>
                <label for="@{{ name }}-@{{ id }}">
                    @{{{ label }}}
                    <em class="is-small" style="color: #ddd;">(@{{ id }})</em>
                </label>
            <td>
        </tr>
    @{{else}}
        <tr><td colspan="2">No results found</td></tr>
    @{{/each}}
</script>

<script id="reference-many-option" type="text/x-handlebars-template">
    @{{#each data}}
        <tr>
            <td style="width:1rem;">
                <input type="checkbox" id="@{{ name }}-@{{ id }}" value="@{{ id }}" data-label="@{{ label }}" @{{#if selected}}checked@{{/if}}>
            </td>
            <td>
                <label for="@{{ name }}-@{{ id }}">
                    @{{{ label }}}
                    <em class="is-small" style="color: #ddd;">(@{{ id }})</em>
                </label>
            <td>
        </tr>
    @{{else}}
        <tr><td colspan="2">No results found</td></tr>
    @{{/each}}
</script>

<script id="inverted-reference-option" type="text/x-handlebars-template">
    @{{#each data}}
        <tr><td>@{{{ label }}}</td><td>@{{ created_at }}</td><td><select data-id="@{{ id }}" class="inverted-ref"><option value="0">No</option><option value="1" @{{#if selected}}selected@{{/if}}>Yes</option></select></td></tr>
    @{{else}}
        <tr><td colspan="99">No results found</td></tr>
    @{{/each}}
</script>

