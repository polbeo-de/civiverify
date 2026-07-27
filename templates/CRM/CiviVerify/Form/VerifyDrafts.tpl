{* Manage workflows which may issue CiviVerify confirmation links. *}
<div class="crm-block crm-form-block crm-civiverify-draft-form-block">
  <h3>{if $editWorkflow}{ts domain='de.polbeo.civicrm.civiverify'}Edit verification draft{/ts}{else}{ts domain='de.polbeo.civicrm.civiverify'}Add verification draft{/ts}{/if}</h3>
  <p class="description">{ts domain='de.polbeo.civicrm.civiverify'}Extensions select the workflow; only this administration page defines its confirmation target. The technical workflow name cannot be changed after it is created.{/ts}</p>
  <table class="form-layout">
    <tr><td class="label">{$form.workflow_name.label}</td><td>{$form.workflow_name.html}</td></tr>
    <tr><td class="label">{$form.label.label}</td><td>{$form.label.html}</td></tr>
    <tr><td class="label">{$form.target_key.label}</td><td>{$form.target_key.html}</td></tr>
    <tr><td class="label">{$form.ttl.label}</td><td>{$form.ttl.html}</td></tr>
    <tr><td></td><td>{$form.buttons.html}</td></tr>
  </table>

  <h3>{ts domain='de.polbeo.civicrm.civiverify'}Configured verification drafts{/ts}</h3>
  <table class="selector-row">
    <thead><tr><th>{ts domain='de.polbeo.civicrm.civiverify'}Workflow{/ts}</th><th>{ts domain='de.polbeo.civicrm.civiverify'}Label{/ts}</th><th>{ts domain='de.polbeo.civicrm.civiverify'}Target{/ts}</th><th>{ts domain='de.polbeo.civicrm.civiverify'}Validity period (seconds){/ts}</th><th></th></tr></thead>
    <tbody>
      {foreach from=$drafts item=draft}
        <tr><td>{$draft.workflow_name|escape}</td><td>{$draft.label|escape}</td><td>{$draft.target_key|escape}</td><td>{$draft.ttl|escape}</td><td><a href="{$draft.edit_url|escape}">{ts domain='de.polbeo.civicrm.civiverify'}Edit{/ts}</a></td></tr>
      {/foreach}
    </tbody>
  </table>
</div>
