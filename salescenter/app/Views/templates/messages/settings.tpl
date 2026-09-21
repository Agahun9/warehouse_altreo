{assign var=platformInfo value=[
  'allegro'=>['Centrum wiadomości oraz Dyskusje i reklamacje (Post Purchase Issues): czat, decyzje w reklamacjach (uznanie / odrzucenie / częściowy zwrot), decyzje o odesłaniu produktu i prośba o zakończenie dyskusji.','Aplikacja Allegro musi mieć uprawnienia allegro:api:messaging i allegro:api:disputes. Po ich włączeniu zaloguj konto Allegro ponownie.'],
  'empik'=>['Wiadomości Mirakl (wątki klienta i operatora EmpikPlace) oraz incydenty zgłaszane przez klientów na pozycjach zamówień – z oznaczaniem jako rozwiązane.','Odpowiedź na incydent trafia do wątku zamówienia; gdy go nie ma, SalesCenter zakłada nowy wątek do klienta.'],
  'mediamarkt'=>['Wiadomości Mirakl (klient i operator MediaMarkt Saturn) oraz incydenty na pozycjach zamówień – z oznaczaniem jako rozwiązane.','Przy kilku sklepach (krajach) każde połączenie synchronizuje się osobno.'],
  'erli'=>['Uwagi kupujących do zamówień i zwroty zgłaszane w ERLI (powód, pozycje, komentarz kupującego, konto do zwrotu).','ERLI nie udostępnia w API rozmów z kupującymi ani odpowiedzi na zwroty – wątki są do odczytu, odpowiadasz w panelu ERLI i ustawiasz tu status.'],
  'temu'=>['Uwagi kupujących zapisane w zamówieniach Temu.','Temu nie udostępnia publicznego API wiadomości – wątki są do odczytu.'],
  'prestashop'=>['Obsługa klienta PrestaShop: wiadomości z formularza kontaktowego i do zamówień (wątki klientów) z odpowiedzią ze SalesCenter.','Klucz webservice potrzebuje uprawnień GET: customer_threads, customer_messages, customers oraz POST: customer_messages. Odpowiedź z API trafia do wątku klienta, ale PrestaShop nie wysyła wtedy e-maila.'],
  'woocommerce'=>['Uwagi klientów wpisane przy składaniu zamówienia.','Odpowiedź zapisuje się jako „notatka dla klienta” w zamówieniu – WooCommerce wysyła ją klientowi e-mailem.'],
  'altreo'=>['Uwagi klientów wpisane przy składaniu zamówienia w sklepie altreo.pl.','Sklep nie ma kanału odpowiedzi – wątki są do odczytu.'],
  'api'=>['Uwagi klientów przesłane przez API własnego sklepu (pole note zamówienia).','Własny sklep nie ma kanału odpowiedzi – wątki są do odczytu.'],
  'morele'=>['Centrum komunikacji Morele: pytania o zamówienie i produkt, reklamacje, zwroty 14-dniowe i inne wiadomości.','Beta: Morele nie publikuje specyfikacji tego API – SalesCenter korzysta z tych samych zasobów co panel sprzedawcy. Jeśli klucz API nie ma do nich dostępu, zobaczysz błąd przy koncie.']
]}
{assign var=dayNames value=[1=>'Pn',2=>'Wt',3=>'Śr',4=>'Cz',5=>'Pt',6=>'So',7=>'Nd']}
<div class="ms-settings">
{foreach $platforms as $code=>$platform}
  {assign var=s value=$platform.settings}
  <section class="om-panel ms-pad ms-settings-card ms-p-{$code}">
    <div class="ms-panel-title">
      <div><span class="ms-badge ms-p-{$code}">{$platform.label|escape}</span><h3>{foreach $platform.kinds as $kind}{$kindLabels[$kind]}{if !$kind@last} · {/if}{/foreach}</h3><p>{$platformInfo[$code][0]}</p></div>
      <span class="om-chip">{$platform.accounts|count} {if $platform.accounts|count eq 1}konto{else}kont{/if}</span>
    </div>
    {if $platform.accounts}
      <ul class="ms-accounts">
      {foreach $platform.accounts as $account}
        {assign var=sync value=$syncStates[$account.id]}
        <li>
          <span class="ms-account-name"><i class="bi bi-{if $account.status eq 'active'}check-circle-fill ms-ok{else}pause-circle{/if}"></i> {$account.name|escape}</span>
          <small>{if $sync.last}ostatnia synchronizacja {$sync.last}{else}jeszcze nie synchronizowano{/if}</small>
          {if $sync.error}<small class="ms-error"><i class="bi bi-exclamation-triangle"></i> {$sync.error|escape}</small>{/if}
          {if $canWrite && $account.status eq 'active'}<form method="post" action="index.php?controller=messages&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="sync"><input type="hidden" name="connection_id" value="{$account.id}"><input type="hidden" name="back" value="tab=settings"><button class="om-btn om-small"><i class="bi bi-arrow-repeat"></i> Synchronizuj</button></form>{/if}
          {if $canWrite && $code eq 'morele' && $account.status eq 'active'}<form method="post" action="index.php?controller=messages&action=save"><input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="probe"><input type="hidden" name="connection_id" value="{$account.id}"><input type="hidden" name="back" value="tab=settings"><button class="om-btn om-small"><i class="bi bi-activity"></i> Diagnostyka Morele</button></form>{/if}
        </li>
      {/foreach}
      </ul>
    {else}
      <p class="ms-muted">Brak podłączonych kont. <a href="orders.php?tab=accounts&add={$code}">Podłącz {$platform.label|escape}</a> w „Konta i import”.</p>
    {/if}
    <p class="ms-note"><i class="bi bi-info-circle"></i> {$platformInfo[$code][1]}</p>
    <form class="om-form ms-settings-form" method="post" action="index.php?controller=messages&action=save">
      <input type="hidden" name="csrf" value="{$csrf|escape}"><input type="hidden" name="operation" value="settings"><input type="hidden" name="platform" value="{$code}"><input type="hidden" name="back" value="tab=settings">
      <fieldset {if !$canWrite}disabled{/if}>
      <div class="ms-toggles">
        <input type="hidden" name="enabled" value="0"><label class="om-check"><input type="checkbox" name="enabled" value="1" {if $s.enabled}checked{/if}> Synchronizuj wiadomości z {$platform.label|escape}</label>
        {if $code eq 'allegro' or $code eq 'empik' or $code eq 'mediamarkt' or $code eq 'morele' or $code eq 'prestashop'}<input type="hidden" name="sync_messages" value="0"><label class="om-check"><input type="checkbox" name="sync_messages" value="1" {if $s.sync_messages}checked{/if}> Wiadomości</label>{/if}
        {if isset($s.sync_notes)}<input type="hidden" name="sync_notes" value="0"><label class="om-check"><input type="checkbox" name="sync_notes" value="1" {if $s.sync_notes}checked{/if}> Uwagi do zamówień</label>{/if}
        {if $code eq 'erli'}<input type="hidden" name="sync_returns" value="0"><label class="om-check"><input type="checkbox" name="sync_returns" value="1" {if $s.sync_returns}checked{/if}> Zwroty</label>{/if}
        {if $code eq 'allegro'}
          <input type="hidden" name="sync_issues" value="0"><label class="om-check"><input type="checkbox" name="sync_issues" value="1" {if $s.sync_issues}checked{/if}> Dyskusje i reklamacje</label>
          <input type="hidden" name="mark_read" value="0"><label class="om-check"><input type="checkbox" name="mark_read" value="1" {if $s.mark_read}checked{/if}> Oznaczaj w Allegro jako przeczytane po otwarciu</label>
        {/if}
        {if $code eq 'empik' or $code eq 'mediamarkt'}
          <input type="hidden" name="sync_incidents" value="0"><label class="om-check"><input type="checkbox" name="sync_incidents" value="1" {if $s.sync_incidents}checked{/if}> Incydenty</label>
          <input type="hidden" name="operator_threads" value="0"><label class="om-check"><input type="checkbox" name="operator_threads" value="1" {if $s.operator_threads}checked{/if}> Wątki z operatorem marketplace</label>
        {/if}
        <input type="hidden" name="autoresponder" value="0"><label class="om-check ms-strong"><input type="checkbox" name="autoresponder" value="1" {if $s.autoresponder}checked{/if}> <i class="bi bi-robot"></i> Autoodpowiedzi włączone</label>
      </div>
      <div class="ms-form-grid">
        <label>Synchronizuj co (min)<input type="number" name="interval" min="2" max="120" value="{$s.interval}"></label>
        {if $code eq 'prestashop'}<label>ID pracownika podpisanego pod odpowiedzią<input type="number" name="employee_id" min="1" max="99999" value="{$s.employee_id}"></label>{/if}
        <label>Pierwsze pobranie – historia (dni)<input type="number" name="history_days" min="1" max="60" value="{$s.history_days}"></label>
        <fieldset class="ms-span-2 ms-hours"><legend>Godziny pracy (dla reguły „poza godzinami pracy” i znacznika {ldelim}godziny{rdelim})</legend>
          <div class="ms-days"><input type="hidden" name="hours_days[]" value="0">{foreach $dayNames as $day=>$name}<label class="ms-day"><input type="checkbox" name="hours_days[]" value="{$day}" {foreach $s.hours.days as $d}{if $d eq $day}checked{/if}{/foreach}><span>{$name}</span></label>{/foreach}</div>
          <label>od<input type="time" name="hours_from" value="{$s.hours.from|escape}"></label>
          <label>do<input type="time" name="hours_to" value="{$s.hours.to|escape}"></label>
        </fieldset>
        <label class="ms-span-2">Podpis (dodawany do odpowiedzi i jako {ldelim}podpis{rdelim})<textarea name="signature" rows="3" maxlength="500" placeholder="Pozdrawiamy,&#10;Zespół obsługi klienta">{$s.signature|escape}</textarea></label>
      </div>
      {if $canWrite}<div class="ms-form-actions"><button class="om-btn om-primary" type="submit"><i class="bi bi-check2"></i> Zapisz {$platform.label|escape}</button></div>{/if}
      </fieldset>
    </form>
  </section>
{/foreach}
</div>
