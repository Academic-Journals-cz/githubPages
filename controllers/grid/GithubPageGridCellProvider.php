<?php

/**
 * @file controllers/grid/GithubPageGridCellProvider.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPageGridCellProvider
 *
 * @brief Cell provider for the GitHub pages grid.
 */

namespace APP\plugins\generic\githubPages\controllers\grid;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;

class GithubPageGridCellProvider extends GridCellProvider
{
    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $githubPage = $row->getData();

        if ($column->getId() === 'path') {
            $dispatcher = $request->getDispatcher();
            return [new LinkAction(
                'details',
                new RedirectAction(
                    $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null) . '/' . $githubPage->getPath(),
                    'githubPage'
                ),
                htmlspecialchars($githubPage->getPath())
            )];
        }
        return parent::getCellActions($request, $row, $column, $position);
    }

    /**
     * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $githubPage = $row->getData();

        switch ($column->getId()) {
            case 'path':
                // The action carries the label.
                return ['label' => ''];
            case 'title':
                return ['label' => $githubPage->getLocalizedTitle()];
            case 'updated':
                $fetchedAt = $githubPage->getLocalizedFetchedAt();
                return ['label' => $fetchedAt ?: '—'];
        }
        return ['label' => ''];
    }
}
