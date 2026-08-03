{* Event payloads deliberately remain unavailable in this operational view. *}
<p class="description">{ts domain='de.polbeo.civicrm.civiverify'}The most recent 100 lifecycle-event deliveries are shown. Event payloads are not displayed.{/ts}</p>

<table class="crm-info-panel">
  <thead>
    <tr>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}ID{/ts}</th>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}Event{/ts}</th>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}Status{/ts}</th>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}Attempts{/ts}</th>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}Created{/ts}</th>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}Next delivery{/ts}</th>
      <th>{ts domain='de.polbeo.civicrm.civiverify'}Last error{/ts}</th>
    </tr>
  </thead>
  <tbody>
    {foreach from=$outboxRows item=row}
      <tr>
        <td>{$row.id|escape}</td>
        <td>{$row.event_name|escape}</td>
        <td>{$row.status|escape}</td>
        <td>{$row.attempt_count|escape}</td>
        <td>{$row.created_date|escape}</td>
        <td>{if $row.delivered_date}{$row.delivered_date|escape}{elseif $row.failed_date}{$row.failed_date|escape}{elseif $row.locked_until}{$row.locked_until|escape}{else}{$row.available_date|escape}{/if}</td>
        <td>{$row.last_error|escape}</td>
      </tr>
    {/foreach}
  </tbody>
</table>
