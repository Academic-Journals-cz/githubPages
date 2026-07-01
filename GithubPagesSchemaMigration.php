<?php

/**
 * @file GithubPagesSchemaMigration.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GithubPagesSchemaMigration
 *
 * @brief Database tables for GitHub pages.
 *
 * context_id is nullable: a NULL context means the page belongs to the whole
 * site (managed from the OJS administration) rather than to a single journal.
 */

namespace APP\plugins\generic\githubPages;

use APP\core\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GithubPagesSchemaMigration extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Don't recreate on upgrade. (Upgraders from 1.0.0 must relax the
        // context_id NOT NULL constraint manually - see README.)
        if (Schema::hasTable('github_pages')) {
            return;
        }

        // One row per page. context_id NULL => site-wide page.
        Schema::create('github_pages', function (Blueprint $table) {
            $table->bigInteger('github_page_id')->autoIncrement();
            $table->string('path', 255);
            $table->bigInteger('context_id')->nullable();
            $table->foreign('context_id', 'github_pages_context_id')
                ->references(Application::getContextDAO()->primaryKeyColumn)
                ->on(Application::getContextDAO()->tableName)
                ->onDelete('cascade');
            $table->index(['context_id'], 'github_pages_context_id_index');
        });

        // Localized settings: title, sourceUrl, content (cached HTML), fetchedAt.
        Schema::create('github_page_settings', function (Blueprint $table) {
            $table->bigIncrements('github_page_setting_id');
            $table->bigInteger('github_page_id');
            $table->foreign('github_page_id', 'github_page_settings_github_page_id')
                ->references('github_page_id')
                ->on('github_pages')
                ->onDelete('cascade');
            $table->index(['github_page_id'], 'github_page_settings_github_page_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->longText('setting_value')->nullable();
            $table->unique(['github_page_id', 'locale', 'setting_name'], 'github_page_settings_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('github_page_settings');
        Schema::drop('github_pages');
    }
}
