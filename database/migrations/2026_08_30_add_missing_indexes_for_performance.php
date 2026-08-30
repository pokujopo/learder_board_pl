<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adding indexes on fields that are queried frequently:
     * - WHERE clauses
     * - ORDER BY clauses
     * - JOIN conditions
     * - Composite indexes for common filter combinations
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Users Table - Auth & Profile Lookups
        |--------------------------------------------------------------------------
        */
        Schema::table('users', function (Blueprint $table) {
            // Fast lookups by email (login)
            $this->createIndexIfNotExists('users', ['email'], 'users_email_index', $table);

            // Fast lookups by role (admin checks)
            $this->createIndexIfNotExists('users', ['role'], 'users_role_index', $table);

            // Fast lookups by phone_number (uniqueness checks, profile searches)
            $this->createIndexIfNotExists('users', ['phone_number'], 'users_phone_number_index', $table);

            // Created_at for sorting/filtering by registration date
            $this->createIndexIfNotExists('users', ['created_at'], 'users_created_at_index', $table);
        });

        /*
        |--------------------------------------------------------------------------
        | Games Table - Game Fetching & Filtering
        |--------------------------------------------------------------------------
        */
        Schema::table('games', function (Blueprint $table) {
            // Filter by code (game lookups)
            $this->createIndexIfNotExists('games', ['code'], 'games_code_index', $table);

            // Filter by public_id (URL routing)
            $this->createIndexIfNotExists('games', ['public_id'], 'games_public_id_index', $table);

            // Filter games by status (is_active) - Already exists but we'll skip it
            // Composite index for common query: active games ordered by date
            $this->createIndexIfNotExists('games', ['is_active', 'start_date'], 'games_is_active_start_date_index', $table);
        });

        /*
        |--------------------------------------------------------------------------
        | Yasuser Table - Referral & Ranking Lookups
        |--------------------------------------------------------------------------
        */
        Schema::table('yasuser', function (Blueprint $table) {
            // Fast lookup by game_id (all competitors in a game)
            $this->createIndexIfNotExists('yasuser', ['game_id'], 'yasuser_game_id_index', $table);

            // Fast lookup by refercode (refercode verification) - Already has unique, but add for lookups
            // Composite: Find user by game + refercode (refercode verification)
            $this->createIndexIfNotExists('yasuser', ['game_id', 'refercode'], 'yasuser_game_id_refercode_index', $table);

            // Sort by inviter_number (leaderboard rankings)
            $this->createIndexIfNotExists('yasuser', ['total_inviter_number'], 'yasuser_total_inviter_number_index', $table);

            // Composite: Leaderboard query (game + sorted by inviter_number)
            $this->createIndexIfNotExists('yasuser', ['game_id', 'total_inviter_number'], 'yasuser_game_id_total_inviter_number_index', $table);

            // last_synced_at for identifying stale records
            $this->createIndexIfNotExists('yasuser', ['last_synced_at'], 'yasuser_last_synced_at_index', $table);

            // Created_at for audit logs
            $this->createIndexIfNotExists('yasuser', ['created_at'], 'yasuser_created_at_index', $table);
        });

        /*
        |--------------------------------------------------------------------------
        | Game_User Table - Game Registration & Rankings
        |--------------------------------------------------------------------------
        */
        Schema::table('game_user', function (Blueprint $table) {
            // Fast lookup: User's games
            $this->createIndexIfNotExists('game_user', ['user_id'], 'game_user_user_id_index', $table);

            // Fast lookup: Game's participants
            $this->createIndexIfNotExists('game_user', ['game_id'], 'game_user_game_id_index', $table);

            // Verify refercode is unique per game (already has composite unique, but we need index for lookups)
            $this->createIndexIfNotExists('game_user', ['game_id', 'refercode'], 'game_user_game_id_refercode_index', $table);

            // Filter verified registrations per game (leaderboard query)
            $this->createIndexIfNotExists('game_user', ['game_id', 'refercode_verified'], 'game_user_game_id_refercode_verified_index', $table);

            // Ranking queries: sort by current_rank
            $this->createIndexIfNotExists('game_user', ['game_id', 'current_rank'], 'game_user_game_id_current_rank_index', $table);

            // Find current user in leaderboard
            $this->createIndexIfNotExists('game_user', ['user_id', 'game_id'], 'game_user_user_id_game_id_index', $table);

            // Track rank changes
            $this->createIndexIfNotExists('game_user', ['rank_movement'], 'game_user_rank_movement_index', $table);

            // Cache invalidation: find recently updated registrations
            $this->createIndexIfNotExists('game_user', ['updated_at'], 'game_user_updated_at_index', $table);
        });

        /*
        |--------------------------------------------------------------------------
        | Personal Access Tokens Table - Auth Token Lookups
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                // Fast lookup by tokenable_id (user's tokens)
                $this->createIndexIfNotExists('personal_access_tokens', ['tokenable_id'], 'personal_access_tokens_tokenable_id_index', $table);

                // Fast lookup by last_used_at (cleanup stale tokens)
                $this->createIndexIfNotExists('personal_access_tokens', ['last_used_at'], 'personal_access_tokens_last_used_at_index', $table);

                // Composite: Find user's API tokens
                $this->createIndexIfNotExists('personal_access_tokens', ['tokenable_id', 'tokenable_type'], 'personal_access_tokens_tokenable_id_tokenable_type_index', $table);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Cache Table - Cache Cleanup Queries
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('cache')) {
            Schema::table('cache', function (Blueprint $table) {
                // Cleanup expired cache entries
                $this->createIndexIfNotExists('cache', ['expiration'], 'cache_expiration_index', $table);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Jobs Table - Queue Processing
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                // Find reserved jobs
                $this->createIndexIfNotExists('jobs', ['reserved'], 'jobs_reserved_index', $table);

                // Find failed jobs
                $this->createIndexIfNotExists('jobs', ['reserved_at'], 'jobs_reserved_at_index', $table);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Companies Table - Company Lookups
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                // Fast lookup by code
                $this->createIndexIfNotExists('companies', ['code'], 'companies_code_index', $table);

                // Filter active companies
                $this->createIndexIfNotExists('companies', ['is_active'], 'companies_is_active_index', $table);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Company_Game Table - Company-Game Relationships
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('company_game')) {
            Schema::table('company_game', function (Blueprint $table) {
                // Find company's games
                $this->createIndexIfNotExists('company_game', ['company_id'], 'company_game_company_id_index', $table);

                // Find game's companies
                $this->createIndexIfNotExists('company_game', ['game_id'], 'company_game_game_id_index', $table);

                // Composite lookup
                $this->createIndexIfNotExists('company_game', ['company_id', 'game_id'], 'company_game_company_id_game_id_index', $table);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'personal_access_tokens_tokenable_id_index');
                $this->dropIndexIfExists($table, 'personal_access_tokens_last_used_at_index');
                $this->dropIndexIfExists($table, 'personal_access_tokens_tokenable_id_tokenable_type_index');
            });
        }

        Schema::table('game_user', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'game_user_user_id_index');
            $this->dropIndexIfExists($table, 'game_user_game_id_index');
            $this->dropIndexIfExists($table, 'game_user_game_id_refercode_index');
            $this->dropIndexIfExists($table, 'game_user_game_id_refercode_verified_index');
            $this->dropIndexIfExists($table, 'game_user_game_id_current_rank_index');
            $this->dropIndexIfExists($table, 'game_user_user_id_game_id_index');
            $this->dropIndexIfExists($table, 'game_user_rank_movement_index');
            $this->dropIndexIfExists($table, 'game_user_updated_at_index');
        });

        Schema::table('yasuser', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'yasuser_game_id_index');
            $this->dropIndexIfExists($table, 'yasuser_game_id_refercode_index');
            $this->dropIndexIfExists($table, 'yasuser_total_inviter_number_index');
            $this->dropIndexIfExists($table, 'yasuser_game_id_total_inviter_number_index');
            $this->dropIndexIfExists($table, 'yasuser_last_synced_at_index');
            $this->dropIndexIfExists($table, 'yasuser_created_at_index');
        });

        Schema::table('games', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'games_code_index');
            $this->dropIndexIfExists($table, 'games_public_id_index');
            $this->dropIndexIfExists($table, 'games_is_active_start_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'users_email_index');
            $this->dropIndexIfExists($table, 'users_role_index');
            $this->dropIndexIfExists($table, 'users_phone_number_index');
            $this->dropIndexIfExists($table, 'users_created_at_index');
        });

        if (Schema::hasTable('company_game')) {
            Schema::table('company_game', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'company_game_company_id_index');
                $this->dropIndexIfExists($table, 'company_game_game_id_index');
                $this->dropIndexIfExists($table, 'company_game_company_id_game_id_index');
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'companies_code_index');
                $this->dropIndexIfExists($table, 'companies_is_active_index');
            });
        }

        if (Schema::hasTable('cache')) {
            Schema::table('cache', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'cache_expiration_index');
            });
        }

        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'jobs_reserved_index');
                $this->dropIndexIfExists($table, 'jobs_reserved_at_index');
            });
        }
    }

    /**
     * Helper: Create index only if it doesn't exist
     */
    private function createIndexIfNotExists(string $table, array $columns, string $indexName, Blueprint $table_blueprint): void
    {
        try {
            // Check if index already exists
            if ($this->indexExists($table, $indexName)) {
                return;
            }

            // Create index using raw SQL to avoid conflicts
            $columnList = implode(',', $columns);
            DB::statement("CREATE INDEX IF NOT EXISTS \"$indexName\" ON \"$table\" (\"" . implode('","', $columns) . "\")");
        } catch (\Exception $e) {
            // Index creation failed, but we continue
            // This might happen if the index already exists with a different name
        }
    }

    /**
     * Helper: Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $dbName = config('database.connections.' . config('database.default') . '.database');
            
            if (config('database.default') === 'sqlite') {
                // SQLite check
                $indexes = DB::select("PRAGMA index_list('$table')");
                foreach ($indexes as $index) {
                    if ($index->name === $indexName) {
                        return true;
                    }
                }
                return false;
            } else {
                // MySQL/PostgreSQL check
                $results = DB::select(
                    "SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
                    [$dbName, $table, $indexName]
                );
                return !empty($results);
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper: Drop index if it exists
     */
    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            if ($this->indexExists($table->getTable(), $indexName)) {
                $table->dropIndex($indexName);
            }
        } catch (\Exception $e) {
            // Index doesn't exist, that's fine
        }
    }
};
