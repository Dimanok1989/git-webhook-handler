<?php

namespace App\Http\Controllers\GitHub;

use App\Jobs\GitHubPusherJob;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;

class Push
{
    /**
     * Каталог с главной версией
     * 
     * @var array
     */
    protected $productions = [
        'Dimanok1989/kolgaev-api' => "/mnt/hdd/www/kolgaev-api",
        'Dimanok1989/kolgaev-disk' => "/mnt/hdd/www/kolgaev-disk",
    ];

    /**
     * Прием данных от GitHub
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function push(Request $request)
    {
        $data = $request->all();

        /** Наименование реппозитория */
        if (!$repo = $data['repository']['full_name'] ?? null)
            return response(['message' => "URL Not found"], 400);

        (new LogManager(app()))->build([
            'driver' => 'daily',
            'path' => storage_path('logs/repo-push/push.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 365,
        ])->info("Запрос обработки хука $repo", $data);

        if (!$path = $this->productions[$repo] ?? null)
            return response(['message' => "Repositry path not found"], 400);

        $files = [];

        foreach ($data['commits'] ?? [] as $commit) {

            foreach ($commit['added'] ?? [] as $file)
                $files[] = $file;

            foreach ($commit['removed'] ?? [] as $file)
                $files[] = $file;

            foreach ($commit['modified'] ?? [] as $file)
                $files[] = $file;
        }

        $composer_update = in_array('composer.json', $files);
        $migrate = false;

        foreach (array_unique($files) as $file) {
            if (strripos($file, 'database/migrations/') !== false) {
                $migrate = true;
                break;
            }
        }

        dispatch(new GitHubPusherJob($repo, $path, $composer_update, $migrate));

        return response()->json([
            'message' => "Webhook accepted",
            'composer_update' => $composer_update,
            'migrate' => $migrate,
        ]);
    }
}

// post '/payload' do
//   request.body.rewind
//   payload_body = request.body.read
//   verify_signature(payload_body)
//   push = JSON.parse(payload_body)
//   "I got some JSON: #{push.inspect}"
// end

// def verify_signature(payload_body)
//   signature = 'sha256=' + OpenSSL::HMAC.hexdigest(OpenSSL::Digest.new('sha256'), ENV['SECRET_TOKEN'], payload_body)
//   return halt 500, "Signatures didn't match!" unless Rack::Utils.secure_compare(signature, request.env['HTTP_X_HUB_SIGNATURE_256'])
// end