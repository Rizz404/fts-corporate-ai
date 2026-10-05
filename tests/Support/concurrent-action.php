<?php

/**
 * Worker process for PostgresConcurrencyTest. Boots the application in the
 * testing environment (same .env.testing database), waits until the shared
 * start time so both workers race, runs one action, and prints JSON.
 *
 *   php tests/Support/concurrent-action.php <scenario> <json-args> <start-unix-ms>
 */

use App\Models\KnowledgeArticle;
use App\Models\TicketDraft;
use App\Models\User;
use App\Modules\Identity\Actions\UpdateUserAccess;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $scenario, $json, $startAt] = $argv + [null, null, '{}', '0'];
$args = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

// Connect before the start line so both workers race on the action itself.
DB::select('select 1');
while ((int) floor(microtime(true) * 1000) < (int) $startAt) {
    usleep(500);
}

try {
    $result = match ($scenario) {
        'confirm' => (function () use ($args) {
            $out = app(ConfirmTicketDraft::class)->handle(User::findOrFail($args['user']), TicketDraft::findOrFail($args['draft']), 1, $args['key']);

            return ['ticket' => $out['ticket']->public_id, 'created' => $out['created']];
        })(),
        'demote' => (function () use ($args) {
            $actor = User::findOrFail($args['actor']);
            $target = User::findOrFail($args['target']);
            app(UpdateUserAccess::class)->handle($actor, $target, [
                'name' => $target->name,
                'email' => $target->email,
                'department_id' => $target->department_id,
                'preferred_locale' => 'id',
                'roles' => ['employee'],
                'support_teams' => [],
                'editor_departments' => [],
                'is_active' => true,
            ]);

            return ['demoted' => $target->id];
        })(),
        'publish' => (function () use ($args) {
            $revision = app(PublishRevision::class)->handle(User::findOrFail($args['actor']), KnowledgeArticle::findOrFail($args['article']));

            return ['published' => $revision->id];
        })(),
        default => throw new InvalidArgumentException("Unknown scenario {$scenario}"),
    };

    echo json_encode(['ok' => true] + $result);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => class_basename($e), 'message' => mb_substr($e->getMessage(), 0, 200)]);
}
