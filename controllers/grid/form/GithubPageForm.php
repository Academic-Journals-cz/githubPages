<?php

/**
 * @file controllers/grid/form/GithubPageForm.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPageForm
 *
 * @brief Form to create / edit a GitHub-sourced page (journal or site level).
 */

namespace APP\plugins\generic\githubPages\controllers\grid\form;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\plugins\generic\githubPages\classes\GithubMarkdownService;
use APP\plugins\generic\githubPages\classes\GithubPagesDAO;
use APP\plugins\generic\githubPages\GithubPagesPlugin;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\notification\Notification;

class GithubPageForm extends \PKP\form\Form
{
    /** @var ?int Context ID (null => site-wide page) */
    public $contextId;

    /** @var ?int Page ID (null when adding) */
    public $githubPageId;

    /** @var GithubPagesPlugin */
    public $plugin;

    public function __construct(GithubPagesPlugin $plugin, ?int $contextId, $githubPageId = null)
    {
        parent::__construct($plugin->getTemplateResource('editGithubPageForm.tpl'));

        $this->contextId = $contextId;
        $this->githubPageId = $githubPageId;
        $this->plugin = $plugin;

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'title', 'required', 'plugins.generic.githubPages.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorRegExp($this, 'path', 'required', 'plugins.generic.githubPages.pathRegEx', '/^[a-zA-Z0-9\/._-]+$/'));

        $form = $this;

        // Path must be unique within the context (or within the site).
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'path', 'required', 'plugins.generic.githubPages.duplicatePath', function ($path) use ($form) {
            /** @var GithubPagesDAO $githubPagesDao */
            $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
            $page = $githubPagesDao->getByPath($form->contextId, $path);
            return !$page || $page->getId() == $form->githubPageId;
        }));

        // The source URL is required and must be a recognizable GitHub URL.
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'sourceUrl', 'required', 'plugins.generic.githubPages.error.badUrl', function ($sourceUrl) {
            $values = is_array($sourceUrl) ? $sourceUrl : [$sourceUrl];
            $hasOne = false;
            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $hasOne = true;
                if (GithubMarkdownService::parseGithubUrl($value) === null) {
                    return false;
                }
            }
            return $hasOne;
        }));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        if ($this->githubPageId) {
            /** @var GithubPagesDAO $githubPagesDao */
            $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
            $githubPage = $githubPagesDao->getById($this->githubPageId);
            $this->setData('path', $githubPage->getPath());
            $this->setData('title', $githubPage->getTitle(null));
            $this->setData('sourceUrl', $githubPage->getSourceUrl(null));
            $this->setData('fetchedAt', $githubPage->getLocalizedFetchedAt());
        }
        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['path', 'title', 'sourceUrl']);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        // Build an example URL (journal path, or "index" for site-wide pages),
        // computed here so the template works without a current context.
        $contextPath = $request->getContext()?->getPath() ?? 'index';
        $exampleUrl = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, $contextPath, 'REPLACEME');
        $exampleUrl = str_replace('REPLACEME', '%PATH%', $exampleUrl);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'githubPageId' => $this->githubPageId,
            'exampleUrl' => $exampleUrl,
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the page and download/render its content from GitHub.
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        /** @var GithubPagesDAO $githubPagesDao */
        $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');

        if ($this->githubPageId) {
            $githubPage = $githubPagesDao->getById($this->githubPageId);
        } else {
            $githubPage = $githubPagesDao->newDataObject();
            $githubPage->setContextId($this->contextId); // null => site-wide
        }

        $githubPage->setPath($this->getData('path'));
        $githubPage->setTitle($this->getData('title'), null);
        $githubPage->setSourceUrl($this->getData('sourceUrl'), null);

        // Download + render the content for every locale before saving.
        $token = $this->plugin->getSetting($this->contextId, 'githubToken');
        $errors = (new GithubMarkdownService())->refreshPage($githubPage, $token ?: null);

        if ($this->githubPageId) {
            $githubPagesDao->updateObject($githubPage);
        } else {
            $githubPagesDao->insertObject($githubPage);
        }

        // Report the outcome.
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        if ($user) {
            $notificationManager = new NotificationManager();
            if (!empty($errors)) {
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    Notification::NOTIFICATION_TYPE_WARNING,
                    ['contents' => __('plugins.generic.githubPages.error.fetchFailed') . ' ' . implode('; ', $errors)]
                );
            } else {
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    Notification::NOTIFICATION_TYPE_SUCCESS,
                    ['contents' => __('plugins.generic.githubPages.pageSaved')]
                );
            }
        }

        return $githubPage->getId();
    }
}
