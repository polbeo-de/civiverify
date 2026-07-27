{* Manage administrator-approved destinations for verification links. *}
<div class="crm-block crm-form-block crm-civiverify-target-form-block">
  <h3>{if $editKey}{ts domain='de.polbeo.civicrm.civiverify'}Edit confirmation target{/ts}{else}{ts domain='de.polbeo.civicrm.civiverify'}Add confirmation target{/ts}{/if}</h3>
  <p class="description">{ts domain='de.polbeo.civicrm.civiverify'}External targets must use HTTPS, except local development environments. A technical key cannot be changed after it is created.{/ts}</p>
  <table class="form-layout">
    <tr class="crm-civiverify-target-form-block-key">
      <td class="label">{$form.key.label}</td>
      <td>{$form.key.html}</td>
    </tr>
    <tr class="crm-civiverify-target-form-block-label">
      <td class="label">{$form.label.label}</td>
      <td>{$form.label.html}</td>
    </tr>
    <tr class="crm-civiverify-target-form-block-route">
      <td class="label">{$form.route.label}</td>
      <td>{$form.route.html}</td>
    </tr>
    <tr><td></td><td>{$form.buttons.html}</td></tr>
  </table>

  <h3>{ts domain='de.polbeo.civicrm.civiverify'}Configured confirmation targets{/ts}</h3>
  <table class="selector-row">
    <thead><tr><th>{ts domain='de.polbeo.civicrm.civiverify'}Key{/ts}</th><th>{ts domain='de.polbeo.civicrm.civiverify'}Label{/ts}</th><th>{ts domain='de.polbeo.civicrm.civiverify'}Route{/ts}</th><th></th></tr></thead>
    <tbody>
      {foreach from=$targets item=target}
        <tr><td>{$target.key|escape}</td><td>{$target.label|escape}</td><td>{$target.route|escape}</td><td><a href="{$target.edit_url|escape}">{ts domain='de.polbeo.civicrm.civiverify'}Edit{/ts}</a></td></tr>
      {/foreach}
    </tbody>
  </table>
</div>
