{{-- Inside the table's <tfoot><tr>: the label next to the totals, the totals under "Kalan". Below `lg` the "Durum" cell is hidden, so the label moves into the cell that spans the columns before it. --}}
<td colspan="3" class="fi-ta-total-label"><span class="fi-hidden-from-lg">{{ __('charges.total') }}</span></td>
<td class="fi-ta-total-label fi-cell-from-lg">{{ __('charges.total') }}</td>
<td class="fi-ta-total-amounts fi-money">
    @foreach ($totals as $total)
        <div>{{ $total }}</div>
    @endforeach
</td>
<td></td>
