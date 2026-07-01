{**
 * templates/githubPagesTab.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Adds a "GitHub Pages" tab to the website settings, containing the grid.
 *}
<tab id="githubPages" label="{translate key="plugins.generic.githubPages.githubPages"}">
	{capture assign=githubPageGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="plugins.generic.githubPages.controllers.grid.GithubPageGridHandler" op="fetchGrid" escape=false}{/capture}
	{load_url_in_div id="githubPageGridContainer" url=$githubPageGridUrl}
</tab>
