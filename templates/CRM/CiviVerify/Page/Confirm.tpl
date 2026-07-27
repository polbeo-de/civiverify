<div class="crm-container civiverify-confirmation">
  <h1>{$civiverify_title|escape}</h1>
  <p>{$civiverify_message|escape}</p>
  {if $civiverify_result eq 'confirm'}
    <form method="post" action="{crmURL p='civicrm/verify'}" autocomplete="off">
      <input type="hidden" name="state" value="{$civiverify_state|escape:'html'}">
      <button type="submit" class="crm-button">{ts domain='de.polbeo.civicrm.civiverify'}Confirm{/ts}</button>
    </form>
  {/if}
</div>
