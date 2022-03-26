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
        $command = "cd {$this->path} && git pull origin master";
        $exec_pull = ["", ">>> {$command}"];
        exec($command, $exec_pull);

        $command = "cd {$this->path} && git push local master";
        $exec_push = ["", ">>> {$command}"];
        exec($command, $exec_push);

        $exec_composer_update = [];

        if ($this->composer_update) {
            $command = "cd {$this->path} && composer update";
            $exec_composer_update = ["", ">>> {$command}"];
            exec($command, $exec_composer_update);
        }

        $exec_migrate = [];

        if ($this->migrate) {
            $command = "php {$this->path}/artisan migrate --force";
            $exec_migrate = ["", ">>> {$command}"];
            exec($command, $exec_migrate);
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
