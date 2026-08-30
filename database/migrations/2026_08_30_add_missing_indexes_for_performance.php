<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            if (!$this->hasIndex('users', 'email')) {
                $table->index('email');
            }

            // Fast lookups by role (admin checks)
            if (!$this->hasIndex('users', 'role')) {
                $table->index('role');
            }

            // Fast lookups by phone_number (uniqueness checks, profile searches)
            if (!$this->hasIndex('users', 'phone_number')) {
                $table->index('phone_number');
            }

            // Created_at for sorting/filtering by registration date
            if (!$this->hasIndex('users', 'created_at')) {
                $table->index('created_at');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Games Table - Game Fetching & Filtering
        |--------------------------------------------------------------------------
        */
        Schema::table('games', function (Blueprint $table) {
            // Filter by code (game lookups)
            if (!$this->hasIndex('games', 'code')) {
                $table->index('code');
            }

            // Filter by public_id (URL routing)
            if (!$this->hasIndex('games', 'public_id')) {
                $table->index('public_id');
            }

            // Filter games by status (is_active)
            if (!$this->hasIndex('games', 'is_active')) {
                $table->index('is_active');
            }

            // Filter by date range (upcoming, live, completed)
            if (!$this->hasIndex('games', 'start_date')) {
                $table->index('start_date');
            }

            if (!$this->hasIndex('games', 'end_date')) {
                $table->index('end_date');
            }

            // Composite index for common query: active games ordered by date
            if (!$this->hasIndex('games', 'is_active_start_date')) {
                $table->index(['is_active', 'start_date']);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Yasuser Table - Referral & Ranking Lookups
        |--------------------------------------------------------------------------
        */
        Schema::table('yasuser', function (Blueprint $table) {
            // Fast lookup by game_id (all competitors in a game)
            if (!$this->hasIndex('yasuser', 'game_id')) {
                $table->index('game_id');
            }

            // Fast lookup by refercode (refercode verification)
            if (!$this->hasIndex('yasuser', 'refercode')) {
                $table->index('refercode');
            }

            // Composite: Find user by game + refercode (refercode verification)
            if (!$this->hasIndex('yasuser', 'game_id_refercode')) {
                $table->index(['game_id', 'refercode']);
            }

            // Sort by inviter_number (leaderboard rankings)
            if (!$this->hasIndex('yasuser', 'total_inviter_number')) {
                $table->index('total_inviter_number');
            }

            // Composite: Leaderboard query (game + sorted by inviter_number)
            if (!$this->hasIndex('yasuser', 'game_id_inviter_number')) {
                $table->index(['game_id', 'total_inviter_number']);
            }

            // last_synced_at for identifying stale records
            if (!$this->hasIndex('yasuser', 'last_synced_at')) {
                $table->index('last_synced_at');
            }

            // Created_at for audit logs
            if (!$this->hasIndex('yasuser', 'created_at')) {
                $table->index('created_at');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Game_User Table - Game Registration & Rankings
        |--------------------------------------------------------------------------
        */
        Schema::table('game_user', function (Blueprint $table) {
            // Fast lookup: User's games
            if (!$this->hasIndex('game_user', 'user_id')) {
                $table->index('user_id');
            }

            // Fast lookup: Game's participants
            if (!$this->hasIndex('game_user', 'game_id')) {
                $table->index('game_id');
            }

            // Verify refercode is unique per game (already has composite unique, but we need index for lookups)
            if (!$this->hasIndex('game_user', 'game_id_refercode')) {
                $table->index(['game_id', 'refercode']);
            }

            // Filter verified registrations per game (leaderboard query)
            if (!$this->hasIndex('game_user', 'game_id_refercode_verified')) {
                $table->index(['game_id', 'refercode_verified']);
            }

            // Ranking queries: sort by current_rank
            if (!$this->hasIndex('game_user', 'game_id_current_rank')) {
                $table->index(['game_id', 'current_rank']);
            }

            // Find current user in leaderboard
            if (!$this->hasIndex('game_user', 'user_id_game_id')) {
                $table->index(['user_id', 'game_id']);
            }

            // Track rank changes
            if (!$this->hasIndex('game_user', 'rank_movement')) {
                $table->index('rank_movement');
            }

            // Cache invalidation: find recently updated registrations
            if (!$this->hasIndex('game_user', 'updated_at')) {
                $table->index('updated_at');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Personal Access Tokens Table - Auth Token Lookups
        |--------------------------------------------------------------------------
        */
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Fast lookup by tokenable_id (user's tokens)
            if (!$this->hasIndex('personal_access_tokens', 'tokenable_id')) {
                $table->index('tokenable_id');
            }

            // Fast lookup by last_used_at (cleanup stale tokens)
            if (!$this->hasIndex('personal_access_tokens', 'last_used_at')) {
                $table->index('last_used_at');
            }

            // Composite: Find user's API tokens
            if (!$this->hasIndex('personal_access_tokens', 'tokenable_id_tokenable_type')) {
                $table->index(['tokenable_id', 'tokenable_type']);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Sessions Table - Session Lookups
        |--------------------------------------------------------------------------
        */
        Schema::table('sessions', function (Blueprint $table) {
            // User sessions (should already exist from base migration, but verify)
            // user_id is already indexed in create_users_table migration
            // last_activity is already indexed in create_users_table migration
        });

        /*
        |--------------------------------------------------------------------------
        | Cache Table - Cache Cleanup Queries
        |--------------------------------------------------------------------------
        */
        Schema::table('cache', function (Blueprint $table) {
            // Cleanup expired cache entries
            if (!$this->hasIndex('cache', 'expiration')) {
                $table->index('expiration');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Jobs Table - Queue Processing
        |--------------------------------------------------------------------------
        */
        Schema::table('jobs', function (Blueprint $table) {
            // Find reserved jobs
            if (!$this->hasIndex('jobs', 'reserved')) {
                $table->index('reserved');
            }

            // Find failed jobs
            if (!$this->hasIndex('jobs', 'reserved_at')) {
                $table->index('reserved_at');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Companies Table - Company Lookups
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                // Fast lookup by code
                if (!$this->hasIndex('companies', 'code')) {
                    $table->index('code');
                }

                // Filter active companies
                if (!$this->hasIndex('companies', 'is_active')) {
                    $table->index('is_active');
                }
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
                if (!$this->hasIndex('company_game', 'company_id')) {
                    $table->index('company_id');
                }

                // Find game's companies
                if (!$this->hasIndex('company_game', 'game_id')) {
                    $table->index('game_id');
                }

                // Composite lookup
                if (!$this->hasIndex('company_game', 'company_id_game_id')) {
                    $table->index(['company_id', 'game_id']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $this->dropIndexIfExists('personal_access_tokens', 'personal_access_tokens_tokenable_id_index');
            $this->dropIndexIfExists('personal_access_tokens', 'personal_access_tokens_last_used_at_index');
            $this->dropIndexIfExists('personal_access_tokens', 'personal_access_tokens_tokenable_id_tokenable_type_index');
        });

        Schema::table('game_user', function (Blueprint $table) {
            $this->dropIndexIfExists('game_user', 'game_user_user_id_index');
            $this->dropIndexIfExists('game_user', 'game_user_game_id_index');
            $this->dropIndexIfExists('game_user', 'game_user_game_id_refercode_index');
            $this->dropIndexIfExists('game_user', 'game_user_game_id_refercode_verified_index');
            $this->dropIndexIfExists('game_user', 'game_user_game_id_current_rank_index');
            $this->dropIndexIfExists('game_user', 'game_user_user_id_game_id_index');
            $this->dropIndexIfExists('game_user', 'game_user_rank_movement_index');
            $this->dropIndexIfExists('game_user', 'game_user_updated_at_index');
        });

        Schema::table('yasuser', function (Blueprint $table) {
            $this->dropIndexIfExists('yasuser', 'yasuser_game_id_index');
            $this->dropIndexIfExists('yasuser', 'yasuser_refercode_index');
            $this->dropIndexIfExists('yasuser', 'yasuser_game_id_refercode_index');
            $this->dropIndexIfExists('yasuser', 'yasuser_total_inviter_number_index');
            $this->dropIndexIfExists('yasuser', 'yasuser_game_id_total_inviter_number_index');
            $this->dropIndexIfExists('yasuser', 'yasuser_last_synced_at_index');
            $this->dropIndexIfExists('yasuser', 'yasuser_created_at_index');
        });

        Schema::table('games', function (Blueprint $table) {
            $this->dropIndexIfExists('games', 'games_code_index');
            $this->dropIndexIfExists('games', 'games_public_id_index');
            $this->dropIndexIfExists('games', 'games_is_active_index');
            $this->dropIndexIfExists('games', 'games_start_date_index');
            $this->dropIndexIfExists('games', 'games_end_date_index');
            $this->dropIndexIfExists('games', 'games_is_active_start_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $this->dropIndexIfExists('users', 'users_email_index');
            $this->dropIndexIfExists('users', 'users_role_index');
            $this->dropIndexIfExists('users', 'users_phone_number_index');
            $this->dropIndexIfExists('users', 'users_created_at_index');
        });

        if (Schema::hasTable('company_game')) {
            Schema::table('company_game', function (Blueprint $table) {
                $this->dropIndexIfExists('company_game', 'company_game_company_id_index');
                $this->dropIndexIfExists('company_game', 'company_game_game_id_index');
                $this->dropIndexIfExists('company_game', 'company_game_company_id_game_id_index');
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $this->dropIndexIfExists('companies', 'companies_code_index');
                $this->dropIndexIfExists('companies', 'companies_is_active_index');
            });
        }

        Schema::table('cache', function (Blueprint $table) {
            $this->dropIndexIfExists('cache', 'cache_expiration_index');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $this->dropIndexIfExists('jobs', 'jobs_reserved_index');
            $this->dropIndexIfExists('jobs', 'jobs_reserved_at_index');
        });
    }

    /**
     * Helper: Check if index exists before creating
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexInfo = \Illuminate\Support\Facades\DB::select(
                "SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [config('database.connections.' . config('database.default') . '.database'), $table, $indexName]
            );
            return !empty($indexInfo);
        } catch (\Exception $e) {
            // SQLite doesn't support information_schema, so we'll rely on Laravel
            return false;
        }
    }

    /**
     * Helper: Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        } catch (\Exception $e) {
            // Index doesn't exist, that's fine
        }
    }
};
