<?php

/**
 * @file GithubPagesPlugin.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPagesPlugin
 *
 * @brief GitHub Markdown Pages plugin main class.
 *
 * Lets managers (per journal) and site administrators (site-wide) expose
 * Markdown files hosted on GitHub as pages. The Markdown is downloaded,
 * rendered to HTML, relative URLs are rewritten to absolute GitHub URLs, the
 * result is sanitized and cached, and finally served at a configurable path.
 *
 * A page with a NULL context belongs to the whole site and is served under the
 * site (index) context; a page with a journal context is served under that
 * journal.
 */

namespace APP\plugins\generic\githubPages;

use APP\core\Application;
use APP\plugins\generic\githubPages\classes\form\GithubPagesSettingsForm;
use APP\plugins\generic\githubPages\classes\GithubPagesDAO;
use APP\plugins\generic\githubPages\controllers\grid\GithubPageGridHandler;
use APP\notification\NotificationManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectAction;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class GithubPagesPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.githubPages.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.githubPages.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                $githubPagesDao = new GithubPagesDAO();
                DAORegistry::registerDAO('GithubPagesDAO', $githubPagesDao);

                // Management tab: journal-level website settings...
                Hook::add('Template::Settings::website', $this->callbackShowSettingsTab(...));
                // ...and site-level administration settings.
                Hook::add('Template::Settings::admin', $this->callbackShowSettingsTab(...));

                // Serve a stored page when its path is requested (works for the
                // site/index context too, since generic plugins load there).
                Hook::add('LoadHandler', $this->callbackHandleContent(...));

                // The grid component that administers the pages.
                Hook::add('LoadComponentHandler', $this->setupGridHandler(...));
            }
            return true;
        }
        return false;
    }

    /**
     * Inject the "GitHub Pages" tab into the website (journal) or admin (site)
     * settings page. Both pages render a top-level <tab> via {call_hook}.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function callbackShowSettingsTab($hookName, $args)
    {
        $templateMgr = $args[1];
        $output = &$args[2];

        $output .= $templateMgr->fetch($this->getTemplateResource('githubPagesTab.tpl'));

        // Let other plugins keep interacting with the hook.
        return false;
    }

    /**
     * Look for a stored page matching the requested path and, if found, attach
     * the handler that renders it. With no journal context this matches
     * site-wide pages (context_id IS NULL).
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function callbackHandleContent($hookName, $args)
    {
        $request = Application::get()->getRequest();

        $page = &$args[0];
        $op = &$args[1];
        $handler = &$args[3];

        // Build the path to look for from page/op/extra arguments.
        $path = $page;
        if ($op !== 'index') {
            $path .= "/{$op}";
        }
        if ($ops = $request->getRequestedArgs()) {
            $path .= '/' . implode('/', $ops);
        }

        $context = $request->getContext();
        /** @var GithubPagesDAO $githubPagesDao */
        $githubPagesDao = DAORegistry::getDAO('GithubPagesDAO');
        // null context => site-wide page.
        $githubPage = $githubPagesDao->getByPath($context?->getId(), $path);

        if ($githubPage) {
            $page = 'pages';
            $op = 'view';
            $handler = new GithubPagesHandler($this, $githubPage);
            return true;
        }
        return false;
    }

    /**
     * Permit requests to the GitHub pages grid handler.
     *
     * @param string $hookName
     * @param array $params
     */
    public function setupGridHandler($hookName, $params)
    {
        $component = &$params[0];
        $componentInstance = &$params[2];
        if ($component == 'plugins.generic.githubPages.controllers.grid.GithubPageGridHandler') {
            $componentInstance = new GithubPageGridHandler($this);
            return true;
        }
        return false;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        // Settings (optional GitHub token) modal - works at both levels.
        $settingsUrl = $router->url($request, null, null, 'manage', null, [
            'verb' => 'settings',
            'plugin' => $this->getName(),
            'category' => 'generic',
        ]);

        if ($context) {
            $manageUrl = $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                null,
                'management',
                'settings',
                ['website'],
                ['uid' => uniqid()],
                'githubPages'
            );
        } else {
            $manageUrl = $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                'index',
                'admin',
                'settings',
                null,
                ['uid' => uniqid()],
                'githubPages'
            );
        }

        array_unshift(
            $actions,
            new LinkAction(
                'settings',
                new AjaxModal($settingsUrl, $this->getDisplayName()),
                __('manager.plugins.settings')
            ),
            new LinkAction(
                'githubPages',
                new RedirectAction($manageUrl),
                __('plugins.generic.githubPages.editAddContent')
            )
        );

        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        // null context id => site-wide settings.
        $contextId = $request->getContext()?->getId();
        $form = new GithubPagesSettingsForm($this, $contextId);

        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(true);
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration()
    {
        return new GithubPagesSchemaMigration();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\githubPages\GithubPagesPlugin', '\GithubPagesPlugin');
}
