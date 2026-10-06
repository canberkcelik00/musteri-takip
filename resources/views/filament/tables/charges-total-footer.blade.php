{{-- Inside the table's <tfoot><tr>: the label sits in whichever cell ends right before "Kalan", the totals under "Kalan". Columns before it: 1 below `md` (Müşteri), 3 from `md` (Müşteri, Açıklama, Vade), 4 from `lg` ("Durum" too). --}}
<td colspan="3" class="fi-ta-total-label fi-cell-from-md"><span class="fi-hidden-from-lg">{{ __('charges.total') }}</span></td>
<td class="fi-ta-total-label fi-cell-below-md">{{ __('charges.total') }}</td>
<td class="fi-ta-total-label fi-cell-from-lg">{{ __('charges.total') }}</td>
<td class="fi-ta-total-amounts fi-money">
    @foreach ($totals as $total)
        <div>{{ $total }}</div>
    @endforeach
</td>
<td></td>
