{**
 * templates/content.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display a stored GitHub page.
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$title}

<div class="page github_markdown_page">
	<h2>{$title|escape}</h2>

	<div class="markdown-body">
		{$content}
	</div>

	{if $sourceUrl}
		<p class="github_source_note">
			{translate key="plugins.generic.githubPages.frontend.source"}
			<a href="{$sourceUrl|escape}" target="_blank" rel="noopener noreferrer">GitHub</a>{if $fetchedAt} &middot; {translate key="plugins.generic.githubPages.frontend.updated"} {$fetchedAt|escape}{/if}
		</p>
	{/if}
</div>

{include file="frontend/components/footer.tpl"}
