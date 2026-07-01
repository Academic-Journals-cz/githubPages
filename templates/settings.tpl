{**
 * templates/settings.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * GitHub Pages plugin settings (optional access token).
 *}
<script>
	$(function() {ldelim}
		$('#githubPagesSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="githubPagesSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="githubPagesSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.githubPages.settings.description"}</div>

	{fbvFormArea id="githubPagesSettingsFormArea"}
		{fbvFormSection label="plugins.generic.githubPages.settings.token" description="plugins.generic.githubPages.settings.tokenHelp"}
			{fbvElement type="text" id="githubToken" value=$githubToken maxlength="255" size=$fbvStyles.size.LARGE password=true}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save" hideCancel=true}
</form>
