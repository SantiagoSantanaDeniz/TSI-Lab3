
<h1>{L_STYLES_TITLE}</h1>

<p>{L_STYLES_ADD_TEXT}</p>

<table cellspacing="1" cellpadding="4" border="0" align="center" class="forumline">
	<tr>
		<th class="thCornerL">{L_STYLE}</th>
		<th class="thTop">{L_TEMPLATE}</th>
		<th class="thCornerR">{L_ACTION}</th>
	</tr>
	<!-- BEGIN styles -->
	<tr>
		<td class="{styles.ROW_CLASS}">{styles.STYLE_NAME}</td>
		<td class="{styles.ROW_CLASS}">{styles.TEMPLATE_NAME}</td>
		<td class="{styles.ROW_CLASS}"><button type="submit" form="phpbb_csrf_actions" formaction="{styles.U_STYLES_INSTALL}" class="csrf-action">{L_INSTALL}</button></td>
	</tr>
	<!-- END styles -->
</table>
