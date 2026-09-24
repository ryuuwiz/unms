<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InstallAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install
        {--name= : Name of the Superadmin user}
        {--email= : Email address for the Superadmin}
        {--password= : Password for the Superadmin}
        {--phone= : Phone / WhatsApp number for the Superadmin}
        {--force : Run non-interactively without confirmation prompts}
        {--skip-migrate : Skip running database migrations}
        {--skip-storage-link : Skip creating public storage symlink}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup database, run production master seeders, and provision initial superadmin user.';

    /**
     * The console command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = [
        'app:setup-production',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Starting GOBILLING Production Setup Wizard...');

        $isForce = (bool) $this->option('force');
        $skipMigrate = (bool) $this->option('skip-migrate');
        $skipStorageLink = (bool) $this->option('skip-storage-link');

        // 1. Resolve Superadmin Name
        $name = $this->option('name');
        if (! $name) {
            $name = $isForce ? 'Super Admin' : $this->ask('Superadmin Display Name', 'Super Admin');
        }

        // 2. Resolve Superadmin Email
        $email = $this->option('email');
        if (! $email) {
            if ($isForce) {
                $email = 'admin@gobilling.id';
            } else {
                while (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = $this->ask('Superadmin Email Address (e.g. admin@gobilling.id)');
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->components->error('Please enter a valid email address.');
                    }
                }
            }
        }

        // 3. Resolve Superadmin Password
        $password = $this->option('password');
        $generatedPassword = null;
        $existingUser = User::where('email', $email)->first();

        if (! $password) {
            if ($isForce) {
                if (! $existingUser) {
                    $generatedPassword = Str::password(16);
                    $password = $generatedPassword;
                }
            } else {
                if ($existingUser) {
                    $this->components->warn("A user with email [{$email}] already exists.");
                    $shouldUpdatePassword = $this->confirm('Do you want to update their password?', false);
                    if ($shouldUpdatePassword) {
                        $password = $this->promptForPassword();
                    }
                } else {
                    $password = $this->promptForPassword();
                }
            }
        }

        // 4. Resolve Phone Number (Optional)
        $phone = $this->option('phone');
        if (! $phone && ! $isForce) {
            $phone = $this->ask('Superadmin Phone/WhatsApp (Optional)', $existingUser->phone ?? '');
            $phone = ! empty($phone) ? $phone : null;
        }

        // 5. Run Database Migrations
        if (! $skipMigrate) {
            $this->components->task('Running database migrations', function () {
                return $this->callSilent('migrate', ['--force' => true]) === 0;
            });
        }

        // 6. Create Storage Symlink
        if (! $skipStorageLink) {
            $this->components->task('Ensuring storage symlink exists', function () {
                $this->callSilent('storage:link');

                return true;
            });
        }

        // 7. Seed Production Master Data (RBAC, Company Profile, WA Templates, Billing Rules)
        $this->components->task('Seeding production master data (Roles, Permissions, Company, WA Templates, Billing Rules)', function () {
            return $this->callSilent('db:seed', [
                '--class' => ProductionSeeder::class,
                '--force' => true,
            ]) === 0;
        });

        // 8. Provision Superadmin Account
        $this->components->task("Provisioning Superadmin account [{$email}]", function () use ($existingUser, $email, $name, $phone, $password) {
            $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);

            $user = $existingUser ?? new User;
            $user->name = $name;
            $user->email = $email;
            if ($phone !== null) {
                $user->phone = $phone;
            }
            $user->status = UserStatus::Active;

            if (! $user->email_verified_at) {
                $user->email_verified_at = Carbon::now();
            }

            if ($password) {
                $user->password = Hash::make($password);
            }

            $user->save();
            $user->syncRoles([$superAdminRole]);

            // Clear Spatie Permission Cache
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            return true;
        });

        $this->newLine();
        $this->components->info('GOBILLING Production Setup Completed Successfully!');
        $this->newLine();

        $this->table(
            ['Parameter', 'Configured Value'],
            [
                ['Name', $name],
                ['Email', $email],
                ['Role', 'super_admin'],
                ['Status', 'Active'],
                ['Phone', $phone ?? '-'],
                ['Password', $generatedPassword ? "<fg=yellow;options=bold>{$generatedPassword}</>" : ($password ? '[Configured]' : '[Unchanged]')],
            ]
        );

        if ($generatedPassword) {
            $this->components->warn('IMPORTANT: Please store the generated password safely. You will not see it again.');
        }

        $this->components->bulletList([
            'Ensure your supervisor daemon is running: supervisorctl status gobilling-worker:*',
            'Ensure crontab entry is configured: * * * * * cd /path/to/unms && php artisan schedule:run >> /dev/null 2>&1',
            'Make sure APP_DEBUG is set to false and APP_ENV is set to production in your .env file.',
        ]);

        return self::SUCCESS;
    }

    /**
     * Interactive prompt for secure password with confirmation.
     */
    protected function promptForPassword(): string
    {
        while (true) {
            $password = $this->secret('Superadmin Password (min. 8 characters)');

            if (strlen((string) $password) < 8) {
                $this->components->error('Password must be at least 8 characters long.');

                continue;
            }

            $confirmPassword = $this->secret('Confirm Superadmin Password');

            if ($password !== $confirmPassword) {
                $this->components->error('Passwords do not match. Please try again.');

                continue;
            }

            return $password;
        }
    }
}
