{**
 * templates/githubPages.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Loads the GitHub pages grid.
 *}
{capture assign=githubPageGridUrl}{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="plugins.generic.githubPages.controllers.grid.GithubPageGridHandler" op="fetchGrid" escape=false}{/capture}
{load_url_in_div id="githubPageGridContainer" url=$githubPageGridUrl}
