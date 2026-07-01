<?php

/**
 * @file classes/GithubPage.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPage
 *
 * @brief Data object representing a GitHub-sourced page.
 */

namespace APP\plugins\generic\githubPages\classes;

class GithubPage extends \PKP\core\DataObject
{
    //
    // Context
    //
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    public function setContextId($contextId)
    {
        return $this->setData('contextId', $contextId);
    }

    //
    // Path (URL slug)
    //
    public function getPath()
    {
        return $this->getData('path');
    }

    public function setPath($path)
    {
        return $this->setData('path', $path);
    }

    //
    // Title (localized)
    //
    public function getTitle($locale)
    {
        return $this->getData('title', $locale);
    }

    public function setTitle($title, $locale)
    {
        return $this->setData('title', $title, $locale);
    }

    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    //
    // Source URL: the GitHub .md file (localized so each language can use its own file)
    //
    public function getSourceUrl($locale)
    {
        return $this->getData('sourceUrl', $locale);
    }

    public function setSourceUrl($sourceUrl, $locale)
    {
        return $this->setData('sourceUrl', $sourceUrl, $locale);
    }

    public function getLocalizedSourceUrl()
    {
        return $this->getLocalizedData('sourceUrl');
    }

    //
    // Content: the cached, rendered HTML (localized)
    //
    public function getContent($locale)
    {
        return $this->getData('content', $locale);
    }

    public function setContent($content, $locale)
    {
        return $this->setData('content', $content, $locale);
    }

    public function getLocalizedContent()
    {
        return $this->getLocalizedData('content');
    }

    //
    // Fetched timestamp: when the content was last downloaded (localized)
    //
    public function getFetchedAt($locale)
    {
        return $this->getData('fetchedAt', $locale);
    }

    public function setFetchedAt($fetchedAt, $locale)
    {
        return $this->setData('fetchedAt', $fetchedAt, $locale);
    }

    public function getLocalizedFetchedAt()
    {
        return $this->getLocalizedData('fetchedAt');
    }
}
