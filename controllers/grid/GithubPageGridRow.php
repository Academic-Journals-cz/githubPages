<?php

/**
 * @file controllers/grid/GithubPageGridRow.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPageGridRow
 *
 * @brief GitHub pages grid row definition (edit / refresh / delete actions).
 */

namespace APP\plugins\generic\githubPages\controllers\grid;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class GithubPageGridRow extends GridRow
{
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        $githubPageId = $this->getId();
        if (empty($githubPageId)) {
            return;
        }

        $router = $request->getRouter();

        // Edit.
        $this->addAction(
            new LinkAction(
                'editGithubPage',
                new AjaxModal(
                    $router->url($request, null, null, 'editGithubPage', null, ['githubPageId' => $githubPageId]),
                    __('grid.action.edit'),
                    null,
                    true
                ),
                __('grid.action.edit'),
                'edit'
            )
        );

        // Refresh content from GitHub.
        $this->addAction(
            new LinkAction(
                'refreshGithubPage',
                new AjaxAction(
                    $router->url($request, null, null, 'refreshGithubPage', null, ['githubPageId' => $githubPageId])
                ),
                __('plugins.generic.githubPages.refresh'),
                'refresh'
            )
        );

        // Delete.
        $this->addAction(
            new LinkAction(
                'delete',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __('common.confirmDelete'),
                    __('grid.action.delete'),
                    $router->url($request, null, null, 'delete', null, ['githubPageId' => $githubPageId]),
                    'negative'
                ),
                __('grid.action.delete'),
                'delete'
            )
        );
    }
}
