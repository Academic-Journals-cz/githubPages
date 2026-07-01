<?php

/**
 * @file GithubPagesHandler.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPagesHandler
 *
 * @brief Render a stored GitHub page when its path is requested.
 */

namespace APP\plugins\generic\githubPages;

use APP\plugins\generic\githubPages\classes\GithubPage;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;

class GithubPagesHandler extends \APP\handler\Handler
{
    /** @var GithubPagesPlugin */
    protected $plugin;

    /** @var GithubPage The page to view */
    protected $githubPage;

    public function __construct(GithubPagesPlugin $plugin, GithubPage $githubPage)
    {
        parent::__construct();
        $this->plugin = $plugin;
        $this->githubPage = $githubPage;
    }

    /**
     * Handle index request (redirect to "view").
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {
        $request->redirect(null, null, 'view', $args);
    }

    /**
     * Display the stored, pre-rendered GitHub content.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function view($args, $request)
    {
        $context = $request->getContext();

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $templateMgr->assign([
            'title' => $this->githubPage->getLocalizedTitle(),
            'content' => $this->githubPage->getLocalizedContent(),
            'sourceUrl' => $this->githubPage->getLocalizedSourceUrl(),
            'fetchedAt' => $this->githubPage->getLocalizedFetchedAt(),
        ]);

        $templateMgr->display($this->plugin->getTemplateResource('content.tpl'));
    }
}
