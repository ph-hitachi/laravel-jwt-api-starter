<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all project documentation (OpenAPI, Postman, Markdown Overview, and DBML)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('==================================================');
        $this->info('🚀 Starting complete documentation generation...');
        $this->info('==================================================');

        // 1. Generate OpenAPI, Postman, and Overview Markdown
        $this->info('👉 Phase 1: Generating OpenAPI schema, Postman collection, and overview.md...');
        $exitCode = $this->call('overview:generate');
        if ($exitCode !== 0) {
            $this->error('❌ Failed during overview/API documentation generation.');
            return $exitCode;
        }

        // 2. Generate DBML Database Schema
        $this->info('');
        $this->info('👉 Phase 2: Generating database.dbml schema...');
        $exitCode = $this->call('dbml:generate');
        if ($exitCode !== 0) {
            $this->error('❌ Failed during DBML schema generation.');
            return $exitCode;
        }

        $this->info('');
        $this->info('==================================================');
        $this->info('✅ All documentation generated successfully inside the /docs folder!');
        $this->info('==================================================');

        return 0;
    }
}
