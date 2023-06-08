<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MagicController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServerController;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\StandaloneDocker;
use App\Models\SwarmDocker;
use App\Models\GithubApp;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;


Route::prefix('magic')->middleware(['auth'])->group(function () {
    Route::get('/servers', [MagicController::class, 'servers']);
    Route::get('/destinations', [MagicController::class, 'destinations']);
    Route::get('/projects', [MagicController::class, 'projects']);
    Route::get('/environments', [MagicController::class, 'environments']);
    Route::get('/project/new', [MagicController::class, 'new_project']);
    Route::get('/environment/new', [MagicController::class, 'new_environment']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'all'])->name('projects');
    Route::get('/project/{project_uuid}', [ProjectController::class, 'show'])->name('project.show');
    Route::get('/project/{project_uuid}/{environment_name}/new', [ProjectController::class, 'new'])->name('project.resources.new');
    Route::get('/project/{project_uuid}/{environment_name}', [ProjectController::class, 'resources'])->name('project.resources');
    Route::get('/project/{project_uuid}/{environment_name}/application/{application_uuid}', [ApplicationController::class, 'configuration'])->name('project.application.configuration');

    Route::get('/project/{project_uuid}/{environment_name}/application/{application_uuid}/deployment',        [ApplicationController::class, 'deployments'])->name('project.application.deployments');

    Route::get(
        '/project/{project_uuid}/{environment_name}/application/{application_uuid}/deployment/{deployment_uuid}',
        [ApplicationController::class, 'deployment']
    )->name('project.application.deployment');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/servers', [ServerController::class, 'all'])->name('server.all');
    Route::get('/server/new', [ServerController::class, 'create'])->name('server.create');
    Route::get('/server/{server_uuid}', [ServerController::class, 'show'])->name('server.show');
    Route::get('/server/{server_uuid}/proxy', [ServerController::class, 'proxy'])->name('server.proxy');
    Route::get('/server/{server_uuid}/private-key', fn () => view('server.private-key'))->name('server.private-key');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/', [Controller::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [Controller::class, 'settings'])->name('settings.configuration');
    Route::get('/settings/emails', [Controller::class, 'emails'])->name('settings.emails');
    Route::get('/profile', fn () => view('profile', ['request' => request()]))->name('profile');
    Route::get('/profile/team', fn () => view('team.show'))->name('team.show');
    Route::get('/profile/team/notifications', fn () => view('team.notifications'))->name('team.notifications');
    Route::get('/command-center', fn () => view('command-center', ['servers' => Server::validated()->get()]))->name('command-center');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/private-key/new', fn () => view('private-key.new'))->name('private-key.new');
    Route::get('/private-key/{private_key_uuid}', fn () => view('private-key.show', [
        'private_key' => PrivateKey::ownedByCurrentTeam()->whereUuid(request()->private_key_uuid)->firstOrFail()
    ]))->name('private-key.show');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/source/new', fn () => view('source.new'))->name('source.new');
    Route::get('/source/github/{github_app_uuid}', function (Request $request) {
        $github_app = GithubApp::where('uuid', request()->github_app_uuid)->first();
        $name = Str::of(Str::kebab($github_app->name))->start('coolify-');
        $settings = InstanceSettings::get();
        $host = $request->schemeAndHttpHost();
        if ($settings->fqdn) {
            $host = $settings->fqdn;
        }
        $installation_path = $github_app->html_url === 'https://github.com' ? 'apps' : 'github-apps';
        $installation_url = "$github_app->html_url/$installation_path/$name/installations/new";
        return view('source.github.show', [
            'github_app' => $github_app,
            'host' => $host,
            'name' => $name,
            'installation_url' => $installation_url,
        ]);
    })->name('source.github.show');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/destination/new', function () {
        $servers = Server::validated()->get();
        $pre_selected_server_uuid = data_get(request()->query(), 'server');
        if ($pre_selected_server_uuid) {
            $server = $servers->firstWhere('uuid', $pre_selected_server_uuid);
            if ($server) {
                $server_id = $server->id;
            }
        }
        return view('destination.new', [
            "servers" => $servers,
            "server_id" => $server_id ?? null,
        ]);
    })->name('destination.new');
    Route::get('/destination/{destination_uuid}', function () {
        $standalone_dockers = StandaloneDocker::where('uuid', request()->destination_uuid)->first();
        $swarm_dockers = SwarmDocker::where('uuid', request()->destination_uuid)->first();
        if (!$standalone_dockers && !$swarm_dockers) {
            abort(404);
        }
        $destination = $standalone_dockers ? $standalone_dockers : $swarm_dockers;
        return view('destination.show', [
            'destination' => $destination->load(['server']),
        ]);
    })->name('destination.show');
});