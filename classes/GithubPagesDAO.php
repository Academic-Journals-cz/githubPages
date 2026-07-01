<?php

/**
 * @file classes/GithubPagesDAO.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPagesDAO
 *
 * @brief Operations for retrieving and modifying GithubPage objects.
 *
 * A NULL context ID denotes a site-wide page (managed from the administration).
 */

namespace APP\plugins\generic\githubPages\classes;

use Illuminate\Support\Facades\DB;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;

class GithubPagesDAO extends \PKP\db\DAO
{
    /**
     * Get a page by its (globally unique) ID.
     *
     * Context ownership is verified by the calling handler's authorize().
     *
     * @return ?GithubPage
     */
    public function getById(int $githubPageId)
    {
        $result = $this->retrieve(
            'SELECT * FROM github_pages WHERE github_page_id = ?',
            [$githubPageId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Get all pages for a context. Pass null for site-wide pages.
     *
     * @param ?int $contextId
     * @param DBResultRange $rangeInfo optional
     *
     * @return DAOResultFactory<GithubPage>
     */
    public function getByContextId($contextId, $rangeInfo = null)
    {
        $where = ($contextId === null) ? 'context_id IS NULL' : 'context_id = ?';
        $params = ($contextId === null) ? [] : [(int) $contextId];

        $result = $this->retrieveRange(
            "SELECT * FROM github_pages WHERE {$where} ORDER BY path",
            $params,
            $rangeInfo
        );
        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Get a page by path within a context. Pass null context for site-wide pages.
     *
     * @param ?int $contextId
     * @param string $path
     *
     * @return ?GithubPage
     */
    public function getByPath($contextId, $path)
    {
        $where = ($contextId === null) ? 'context_id IS NULL' : 'context_id = ?';
        $params = ($contextId === null) ? [$path] : [(int) $contextId, $path];

        $result = $this->retrieve(
            "SELECT * FROM github_pages WHERE {$where} AND path = ?",
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Insert a new page.
     *
     * @param GithubPage $githubPage
     *
     * @return int The new page ID
     */
    public function insertObject($githubPage)
    {
        $contextId = $githubPage->getContextId();
        $this->update(
            'INSERT INTO github_pages (context_id, path) VALUES (?, ?)',
            [$contextId === null ? null : (int) $contextId, $githubPage->getPath()]
        );
        $githubPage->setId($this->getInsertId());
        $this->updateLocaleFields($githubPage);
        return $githubPage->getId();
    }

    /**
     * Update an existing page.
     *
     * @param GithubPage $githubPage
     */
    public function updateObject($githubPage)
    {
        $contextId = $githubPage->getContextId();
        $this->update(
            'UPDATE github_pages SET context_id = ?, path = ? WHERE github_page_id = ?',
            [
                $contextId === null ? null : (int) $contextId,
                $githubPage->getPath(),
                (int) $githubPage->getId(),
            ]
        );
        $this->updateLocaleFields($githubPage);
    }

    /**
     * Delete a page by ID.
     */
    public function deleteById(int $githubPageId): int
    {
        return DB::table('github_pages')
            ->where('github_page_id', '=', $githubPageId)
            ->delete();
    }

    /**
     * Delete a page object.
     *
     * @param GithubPage $githubPage
     */
    public function deleteObject($githubPage)
    {
        $this->deleteById($githubPage->getId());
    }

    /**
     * Create a fresh page object.
     *
     * @return GithubPage
     */
    public function newDataObject()
    {
        return new GithubPage();
    }

    /**
     * Build a page object from a database row.
     *
     * @return GithubPage
     */
    public function _fromRow($row)
    {
        $githubPage = $this->newDataObject();
        $githubPage->setId($row['github_page_id']);
        $githubPage->setPath($row['path']);
        $githubPage->setContextId($row['context_id']);

        $this->getDataObjectSettings('github_page_settings', 'github_page_id', $row['github_page_id'], $githubPage);
        return $githubPage;
    }

    /**
     * Localized field names.
     */
    public function getLocaleFieldNames(): array
    {
        return ['title', 'sourceUrl', 'content', 'fetchedAt'];
    }

    /**
     * Persist the localized fields.
     */
    public function updateLocaleFields(&$githubPage)
    {
        $this->updateDataObjectSettings(
            'github_page_settings',
            $githubPage,
            ['github_page_id' => $githubPage->getId()]
        );
    }
}
