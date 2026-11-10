<?php

use App\Services\Research\LocalLabelingServer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$response = $app->make(LocalLabelingServer::class)->handle(Request::capture(), (string) getenv('RESEARCH_DATASET'), (string) getenv('RESEARCH_LOCAL_TOKEN'));
$response->headers->set('Cache-Control', 'no-store');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'no-referrer');
$response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' https: http:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
$response->send();
