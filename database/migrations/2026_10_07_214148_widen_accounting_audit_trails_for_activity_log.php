<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Original definitions, kept verbatim so down() can restore them.
     */
    private const ACTION_ENUM = "enum('create','update','delete','post','reverse','void','waive','lock','close','reopen','import','migrate','export','recurring_fee_generated')";

    private const ACTOR_ENUM = "enum('user','system','ai','cron','import')";

    /**
     * Widen accounting_audit_trails so AuditActivityLog can write to it.
     *
     * The middleware emits actions (login, logout, failed_login,
     * permission_denied, module_access:*) that the accounting-only action
     * enum rejects, writes actor_type 'guest' for unauthenticated requests,
     * and passes entity_id = null. Three additive changes fix that.
     */
    public function up(): void
    {
        Schema::table('accounting_audit_trails', function (Blueprint $table) {
            // 1. action: enum -> varchar(60) so module_access:*, login, ...
            $table->string('action', 60)->change();

            // 3. entity_id: NOT NULL -> nullable (middleware passes null)
            $table->unsignedBigInteger('entity_id')->nullable()->change();
        });

        // 2. actor_type: extend the enum with 'guest'. Raw statement: a full
        //    ->change() on an enum would rebuild the column definition.
        DB::statement(
            "ALTER TABLE accounting_audit_trails MODIFY actor_type ENUM('user','system','ai','cron','import','guest') NOT NULL DEFAULT 'user'"
        );
    }

    /**
     * Restore the original column definitions. Rows written with the widened
     * vocabulary cannot survive the narrowing, so each step is skipped (with
     * a warning) when incompatible rows are present instead of failing the
     * rollback half-way through.
     */
    public function down(): void
    {
        $actionRow = DB::selectOne(
            "SELECT COUNT(*) AS c FROM accounting_audit_trails WHERE action NOT IN ('create','update','delete','post','reverse','void','waive','lock','close','reopen','import','migrate','export','recurring_fee_generated')"
        );
        if ((int) $actionRow->c === 0) {
            DB::statement('ALTER TABLE accounting_audit_trails MODIFY action '.self::ACTION_ENUM.' NOT NULL');
        } else {
            Log::warning('accounting_audit_trails: action column NOT restored — '.$actionRow->c.' row(s) use values outside the original enum.');
        }

        $actorRow = DB::selectOne("SELECT COUNT(*) AS c FROM accounting_audit_trails WHERE actor_type = 'guest'");
        if ((int) $actorRow->c === 0) {
            DB::statement('ALTER TABLE accounting_audit_trails MODIFY actor_type '.self::ACTOR_ENUM." NOT NULL DEFAULT 'user'");
        } else {
            Log::warning('accounting_audit_trails: actor_type column NOT restored — '.$actorRow->c." row(s) use actor_type 'guest'.");
        }

        $nullEntityRow = DB::selectOne('SELECT COUNT(*) AS c FROM accounting_audit_trails WHERE entity_id IS NULL');
        if ((int) $nullEntityRow->c === 0) {
            Schema::table('accounting_audit_trails', function (Blueprint $table) {
                $table->unsignedBigInteger('entity_id')->nullable(false)->change();
            });
        } else {
            Log::warning('accounting_audit_trails: entity_id column NOT restored to NOT NULL — '.$nullEntityRow->c.' row(s) have a NULL entity_id.');
        }
    }
};
