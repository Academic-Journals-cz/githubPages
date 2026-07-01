<?php

/**
 * @file classes/form/GithubPagesSettingsForm.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPagesSettingsForm
 *
 * @brief Plugin-wide settings (optional GitHub access token).
 */

namespace APP\plugins\generic\githubPages\classes\form;

use APP\plugins\generic\githubPages\GithubPagesPlugin;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class GithubPagesSettingsForm extends Form
{
    /** @var int Context ID */
    public $contextId;

    /** @var GithubPagesPlugin */
    public $plugin;

    public function __construct(GithubPagesPlugin $plugin, ?int $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;

        parent::__construct($plugin->getTemplateResource('settings.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $this->setData('githubToken', $this->plugin->getSetting($this->contextId, 'githubToken'));
        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['githubToken']);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $this->plugin->updateSetting($this->contextId, 'githubToken', trim((string) $this->getData('githubToken')), 'string');
        return parent::execute(...$functionArgs);
    }
}
