<?php
use SLiMS\Table\Schema;
use SLiMS\Table\Blueprint;
use SLiMS\DB;

class CreateSibiDocs extends \SLiMS\Migration\Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    function up()
    {
        $pdo = DB::getInstance();
        // Aman untuk instalasi baru maupun database yang pernah memasang SIBI.
        if (!$this->hasTable($pdo, 'sibi_docs')) {
        Schema::create('sibi_docs', function (Blueprint $table) {
            // Engine and Collation
            $table->engine = 'MyISAM';
            $table->charset = 'utf8mb4'; 
            $table->collation = 'utf8mb4_unicode_ci';

            // Core String Fields
            $table->number('id')->notNull();
            $table->text('title')->notNull();
            $table->string('slug', 100)->notNull();
            $table->text('image')->nullable();
            $table->text('attachment')->nullable();
            $table->text('description')->nullable();

            // Metadata
            $table->date('published_year')->nullable();
            $table->string('class', 50)->nullable();
            $table->string('level', 100)->nullable();
            $table->text('writer')->nullable();
            $table->text('reviewer')->nullable();
            $table->string('translator', 255)->nullable();
            $table->string('adapter', 255)->nullable();
            $table->string('designer', 255)->nullable();
            $table->string('cover_designer', 255)->nullable();
            $table->string('ilustrator', 255)->nullable();
            $table->string('editor', 255)->nullable();
            $table->string('aligner', 255)->nullable();
            $table->string('publisher', 255)->nullable();
            $table->text('contributor')->nullable();
            $table->string('language', 100)->nullable();
            $table->text('context')->nullable();
            $table->string('subject', 100)->nullable();
            $table->string('format', 50)->nullable();
            $table->string('isbn', 50)->nullable();
            $table->string('curriculum', 100)->nullable();
            $table->string('collation', 100)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('edition', 50)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('book_type', 100)->nullable();
            $table->string('version', 20)->nullable();

            // Pricing Fields
            $table->decimal('price_zone_1', 10, 2)->nullable();
            $table->decimal('price_zone_2', 10, 2)->nullable();
            $table->decimal('price_zone_3', 10, 2)->nullable();
            $table->decimal('price_zone_4', 10, 2)->nullable();
            $table->decimal('price_zone_5A', 10, 2)->nullable();
            $table->decimal('price_zone_5B', 10, 2)->nullable();

            // Timestamps
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();
            $table->datetime('deleted_at')->nullable();
            
            // indexes
            $table->primary('id');
            $table->fulltext('title');
            $table->fulltext('description');
            $table->fulltext('writer');
            $table->index('isbn');
            $table->index('slug');
            $table->index('category');
        });

        } // end create table when absent
        if (!$this->hasColumn($pdo, 'biblio', 'sibi_doc_id')) {
            // NULL for existing records, avoids unique-index collisions with default 0.
            $pdo->exec('ALTER TABLE `biblio` ADD COLUMN `sibi_doc_id` BIGINT NULL DEFAULT NULL');
        }
        if (!$this->hasIndex($pdo, 'biblio', 'sibi_doc_id_unq') && !$this->hasUniqueColumnIndex($pdo, 'biblio', 'sibi_doc_id')) {
            $pdo->exec('ALTER TABLE `biblio` ADD UNIQUE KEY `sibi_doc_id_unq` (`sibi_doc_id`)');
        }
        // Do not ALTER core image columns: this is unnecessary for storing cover filenames.

    }

    private function hasTable($pdo, $name) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $q->execute([$name]); return (bool)$q->fetchColumn();
    }
    private function hasColumn($pdo, $table, $column) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([$table,$column]); return (bool)$q->fetchColumn();
    }
    private function hasIndex($pdo, $table, $name) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $q->execute([$table,$name]); return (bool)$q->fetchColumn();
    }
    private function hasUniqueColumnIndex($pdo, $table, $column) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0');
        $q->execute([$table,$column]); return (bool)$q->fetchColumn();
    }
    /** Rollback deliberately non-destructive to preserve imported bibliographies. */
    function down() { /* No destructive changes on disable/uninstall. */ }
}
