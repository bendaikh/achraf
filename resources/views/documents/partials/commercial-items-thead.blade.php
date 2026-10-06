@if(!empty($ghost))
<tr>
    <th width="105"></th>
    <th width="169"></th>
    <th width="37"></th>
    <th width="69"></th>
    <th width="42"></th>
    <th width="42"></th>
    <th width="63"></th>
</tr>
@else
<tr>
    <th class="text-left" width="105">Réf</th>
    <th class="text-left" width="169">Désignation</th>
    <th class="text-right" width="37">Qté</th>
    <th class="text-right" width="69">Prix unit. HT</th>
    <th class="text-center" width="42">TVA</th>
    <th class="text-right" width="42">Remise</th>
    <th class="text-right" width="63">Total TTC</th>
</tr>
@endif
