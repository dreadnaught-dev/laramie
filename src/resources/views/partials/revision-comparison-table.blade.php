<table class="table revisions">
    <thead>
        <tr><td></td><th>{{ $leftLabel }}</th><th>{{ $rightLabel }}</th></tr>
    </thead>
    @foreach ($diffs as $diff)
        <tr class="is-changed">
            <th>{{ $diff->label }}</th>
            <td class="left">{!! $diff->left !!}</td>
            <td class="right">{!! $diff->right !!}</td>
        </tr>
    @endforeach
</table>
