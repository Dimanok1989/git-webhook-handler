<?php

namespace App\Jobs;

use Illuminate\Log\LogManager;

class GitHubPusherJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $repo,
        protected string $path,
        protected bool $composer_update = false,
        protected bool $migrate = false
    ) {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $exec_pull = ["", ">>> cd {$this->path} && git pull origin master"];
        exec("cd {$this->path} && git pull origin master", $exec_pull);

        $exec_push = ["", ">>> cd {$this->path} && git push local master"];
        exec("cd {$this->path} && git push local master", $exec_push);

        $exec_composer_update = [];

        if ($this->composer_update) {
            $exec_composer_update = ["", ">>> cd {$this->path} && composer update"];
            exec("cd {$this->path} && composer update", $exec_composer_update);
        }

        $exec_migrate = [];

        if ($this->migrate) {
            $exec_migrate = ["", ">>> cd {$this->path} && php artisan migrate --force"];
            exec("cd {$this->path} && php artisan migrate", $exec_migrate);
        }

        $exec = [
            ...$exec_pull,
            ...$exec_push,
            ...$exec_composer_update,
            ...$exec_migrate,
            ...[""],
        ];

        $info = implode("\n", $exec);

        (new LogManager(app()))->build([
            'driver' => 'daily',
            'path' => storage_path('logs/repo-push/push.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 90,
        ])->debug("Обработка хука {$this->repo} завершена\n{$info}");
    }
}
