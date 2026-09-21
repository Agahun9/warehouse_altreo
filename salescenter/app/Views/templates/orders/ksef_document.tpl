{if $doc.kind eq 'invoice' or $doc.kind eq 'invoice_correction'}{assign var=ksefSub value=$ksefSubmissions[$doc.id]|default:null}{assign var=ksefTarget value=$ksefTargets[$doc.id]|default:null}
<div class="om-ksef-doc">
  {if !$ksefSub}<span class="om-ksef-state is-none"><i class="bi bi-cloud"></i> Nie wysłano do KSeF</span>
  {elseif $ksefSub.state eq 'accepted'}<span class="om-ksef-state is-accepted" title="{$ksefSub.message|escape}"><i class="bi bi-patch-check-fill"></i> Wysłano i przyjęto w KSeF · {$ksefSub.ksef_number|escape}</span>{if $ksefSub.has_upo}<span class="om-ksef-state is-accepted"><i class="bi bi-envelope-check-fill"></i> UPO odebrane</span>{else}<span class="om-ksef-state is-processing"><i class="bi bi-hourglass-split"></i> Oczekiwanie na UPO</span>{/if}
  {elseif $ksefSub.state eq 'processing'}<span class="om-ksef-state is-processing" title="{$ksefSub.message|escape}"><i class="bi bi-hourglass-split"></i> KSeF: przetwarzanie</span>
  {elseif $ksefSub.state eq 'rejected'}<span class="om-ksef-state is-rejected" title="{$ksefSub.message|escape}"><i class="bi bi-x-octagon-fill"></i> KSeF: odrzucona</span>
  {else}<span class="om-ksef-state is-rejected" title="{$ksefSub.message|escape}"><i class="bi bi-exclamation-triangle-fill"></i> KSeF: błąd wysyłki</span>{/if}
  {if $ksefSub}<span class="om-ksef-env om-ksef-mode-{$ksefSub.environment|escape}">{if $ksefSub.environment eq 'production'}PRODUKCJA{else}SANDBOX{/if}</span>{/if}
  {if $canWrite}
    {if !$ksefTarget}<span class="om-ksef-message" style="color:#8b95a8">Seria nie ma przypisanego konta KSeF.</span>{elseif !$ksefSub or $ksefSub.environment ne $ksefTarget.environment or ($ksefSub.state eq 'rejected' or $ksefSub.state eq 'error' or $ksefSub.state eq 'sending')}
    <form method="post" action="?controller=orders&action=save" {if $ksefTarget.environment eq 'production'}data-confirm-action="Wysłać {$doc.number|escape} do produkcyjnego KSeF ({$ksefTarget.name|escape})? Faktura zostanie wystawiona w KSeF i nie da się jej usunąć."{/if}><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="ksef_send"><input type="hidden" name="tab" value="{$ksefTab|default:'documents'}"><input type="hidden" name="order_id" value="{$ksefOrderId|default:0}"><input type="hidden" name="document_id" value="{$doc.id}"><button class="om-btn om-small" {if !$ksefTarget.ready}disabled title="Konto KSeF „{$ksefTarget.name|escape}” nie ma tokena dla swojego trybu — uzupełnij je w Ustawieniach ogólnych"{else}title="Konto KSeF: {$ksefTarget.name|escape}"{/if}><i class="bi bi-send"></i> Wyślij do KSeF · {if $ksefTarget.environment eq 'production'}produkcja{else}sandbox{/if}</button></form>
    {/if}
    {if $ksefSub && ($ksefSub.state eq 'processing' or ($ksefSub.state eq 'accepted' && !$ksefSub.has_upo))}
    <form method="post" action="?controller=orders&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="ksef_refresh"><input type="hidden" name="tab" value="{$ksefTab|default:'documents'}"><input type="hidden" name="order_id" value="{$ksefOrderId|default:0}"><input type="hidden" name="document_id" value="{$doc.id}"><button class="om-btn om-small"><i class="bi bi-arrow-repeat"></i> {if $ksefSub.state eq 'processing'}Odśwież status{else}Pobierz UPO{/if}</button></form>
    {/if}
  {/if}
  {if $ksefSub && $ksefSub.state eq 'accepted' && $ksefSub.has_upo}<a class="om-btn om-small" href="?controller=orders&action=ksefdownload&kind=upo&id={$doc.id}"><i class="bi bi-file-earmark-check"></i> UPO</a>{/if}
  <a class="om-btn om-small" href="?controller=orders&action=ksefdownload&kind=xml&id={$doc.id}" title="Podgląd XML FA(3)"><i class="bi bi-filetype-xml"></i> XML</a>
  {if $ksefSub && ($ksefSub.state eq 'rejected' or $ksefSub.state eq 'error' or $ksefSub.state eq 'sending') && $ksefSub.message}<small class="om-ksef-message">{$ksefSub.message|escape}</small>{/if}
</div>
{/if}
