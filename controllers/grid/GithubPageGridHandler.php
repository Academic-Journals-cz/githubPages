<?php

/**
 * @file controllers/grid/GithubPageGridHandler.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPageGridHandler
 *
 * @brief Handle GitHub pages grid requests (journal managers and site admins).
 */

namespace APP\plugins\generic\githubPages\controllers\grid;

use APP\notification\NotificationManager;
use APP\plugins\generic\githubPages\classes\GithubMarkdownService;
use APP\plugins\generic\githubPages\classes\GithubPagesDAO;
use APP\plugins\generic\githubPages\controllers\grid\form\GithubPageForm;
use APP\plugins\generic\githubPages\GithubPagesPlugin;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class GithubPageGridHandler extends GridHandler
{
    /** @var GithubPagesPlugin */
    public $plugin;

    public function __construct(GithubPagesPlugin $plugin)
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['index', 'fetchGrid', 'fetchRow', 'addGithubPage', 'editGithubPage', 'updateGithubPage', 'refreshGithubPage', 'delete']
        );
        $this->plugin = $plugin;
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $context = $request->getContext();

        // Journal managers inside a journal; site admins at the site level.
        if ($context) {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        } else {
            $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        }

        // Make sure any referenced page belongs to the current level.
        $githubPageId = $request->getUserVar('githubPageId');
        if ($githubPageId) {
            /** @var GithubPagesDAO $githubPagesDao */
            $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
            $githubPage = $githubPagesDao->getById((int) $githubPageId);
            if (!$githubPage || $githubPage->getContextId() != $context?->getId()) {
                return false;
            }
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $context = $request->getContext();

        $this->setTitle('plugins.generic.githubPages.githubPages');
        $this->setEmptyRowText('plugins.generic.githubPages.noneCreated');

        /** @var GithubPagesDAO $githubPagesDao */
        $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
        // null context => site-wide pages.
        $this->setGridDataElements($githubPagesDao->getByContextId($context?->getId()));

        // Grid-level "add" action.
        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addGithubPage',
                new AjaxModal(
                    $router->url($request, null, null, 'addGithubPage'),
                    __('plugins.generic.githubPages.addGithubPage'),
                ),
                __('plugins.generic.githubPages.addGithubPage'),
                'add_item'
            )
        );

        // Columns.
        $cellProvider = new GithubPageGridCellProvider();
        $this->addColumn(new GridColumn(
            'title',
            'plugins.generic.githubPages.pageTitle',
            null,
            'controllers/grid/gridCell.tpl',
            $cellProvider
        ));
        $this->addColumn(new GridColumn(
            'path',
            'plugins.generic.githubPages.path',
            null,
            'controllers/grid/gridCell.tpl',
            $cellProvider
        ));
        $this->addColumn(new GridColumn(
            'updated',
            'plugins.generic.githubPages.updated',
            null,
            'controllers/grid/gridCell.tpl',
            $cellProvider
        ));
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    public function getRowInstance()
    {
        return new GithubPageGridRow();
    }

    //
    // Public grid actions
    //
    /**
     * Display the grid's containing page.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function index($args, $request)
    {
        $form = new \PKP\form\Form($this->plugin->getTemplateResource('githubPages.tpl'));
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Add a new page (delegates to edit with no ID).
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function addGithubPage($args, $request)
    {
        return $this->editGithubPage($args, $request);
    }

    /**
     * Present the edit form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function editGithubPage($args, $request)
    {
        $githubPageId = $request->getUserVar('githubPageId');
        $context = $request->getContext();
        $this->setupTemplate($request);

        $form = new GithubPageForm($this->plugin, $context?->getId(), $githubPageId);
        $form->initData();
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Save the edit form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function updateGithubPage($args, $request)
    {
        $githubPageId = $request->getUserVar('githubPageId');
        $context = $request->getContext();
        $this->setupTemplate($request);

        $form = new GithubPageForm($this->plugin, $context?->getId(), $githubPageId);
        $form->readInputData();

        if ($form->validate()) {
            $form->execute();
            return DAO::getDataChangedEvent();
        }
        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Re-download a page's content from GitHub.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function refreshGithubPage($args, $request)
    {
        $githubPageId = $request->getUserVar('githubPageId');
        $context = $request->getContext();

        /** @var GithubPagesDAO $githubPagesDao */
        $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
        $githubPage = $githubPagesDao->getById((int) $githubPageId);

        if ($githubPage) {
            $token = $this->plugin->getSetting($context?->getId(), 'githubToken');
            $errors = (new GithubMarkdownService())->refreshPage($githubPage, $token ?: null);
            $githubPagesDao->updateObject($githubPage);

            $notificationManager = new NotificationManager();
            $user = $request->getUser();
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
                    ['contents' => __('plugins.generic.githubPages.refreshed')]
                );
            }
        }

        return DAO::getDataChangedEvent($githubPageId);
    }

    /**
     * Delete a page.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function delete($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $githubPageId = $request->getUserVar('githubPageId');

        /** @var GithubPagesDAO $githubPagesDao */
        $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
        $githubPage = $githubPagesDao->getById((int) $githubPageId);
        if ($githubPage) {
            $githubPagesDao->deleteObject($githubPage);
        }

        return DAO::getDataChangedEvent();
    }
}
