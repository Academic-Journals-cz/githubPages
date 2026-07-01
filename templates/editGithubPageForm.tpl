{**
 * templates/editGithubPageForm.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form for creating / editing a GitHub page (journal or site level).
 *}
<script>
	$(function() {ldelim}
		$('#githubPageForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

{capture assign=actionUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="plugins.generic.githubPages.controllers.grid.GithubPageGridHandler" op="updateGithubPage" escape=false}{/capture}
<form class="pkp_form" id="githubPageForm" method="post" action="{$actionUrl}">
	{csrf}
	{if $githubPageId}
		<input type="hidden" name="githubPageId" value="{$githubPageId|escape}" />
	{/if}

	{fbvFormArea id="githubPagesFormArea" class="border"}
		{fbvFormSection}
			{fbvElement type="text" label="plugins.generic.githubPages.path" id="path" value=$path maxlength="255" inline=true size=$fbvStyles.size.MEDIUM}
			{fbvElement type="text" label="plugins.generic.githubPages.pageTitle" id="title" value=$title maxlength="255" inline=true multilingual=true size=$fbvStyles.size.MEDIUM}
		{/fbvFormSection}

		{fbvFormSection}
			{translate key="plugins.generic.githubPages.viewInstructions" pagesPath=$exampleUrl}
		{/fbvFormSection}

		{fbvFormSection label="plugins.generic.githubPages.sourceUrl" description="plugins.generic.githubPages.sourceUrl.help" for="sourceUrl"}
			{fbvElement type="text" multilingual=true name="sourceUrl" id="sourceUrl" value=$sourceUrl maxlength="2048" size=$fbvStyles.size.LARGE}
		{/fbvFormSection}

		{if $fetchedAt}
			{fbvFormSection}
				<span class="pkp_form_instructions">{translate key="plugins.generic.githubPages.updated"}: {$fetchedAt|escape}</span>
			{/fbvFormSection}
		{/if}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save" hideCancel=true}
</form>
