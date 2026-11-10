<?php

namespace App\Services\Research;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;

final class LocalLabelingServer
{
    public function __construct(private LabelingWorkspace $workspace) {}

    public function handle(Request $request, string $dataset, string $token): Response
    {
        try {
            StudyFiles::localOnly();
            if (! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true) || ! in_array($request->getHost(), ['127.0.0.1', 'localhost', '[::1]'], true)) {
                return new Response('Loopback access only.', 403);
            }
            $path = $request->getPathInfo();
            if ($request->isMethod('GET') && str_starts_with($path, '/build/assets/')) {
                if (preg_match('~^/build/assets/[a-zA-Z0-9_.-]+\.(js|css|woff2?)$~D', $path, $match) !== 1 || ! is_file(public_path($path)) || is_link(public_path($path))) {
                    return new Response('Not found', 404);
                }

                return new Response(file_get_contents(public_path($path)), 200, ['Content-Type' => match ($match[1]) {
                    'js' => 'application/javascript', 'css' => 'text/css', default => 'font/woff2'
                }]);
            }
            if ($request->isMethod('GET') && $path === '/') {
                $manifest = StudyFiles::readJson(public_path('build/manifest.json'));
                $entry = $manifest['resources/js/research/LabelingApp.jsx'] ?? throw new RuntimeException('Run npm run build first.');
                $scripts = ['/build/'.$entry['file']];
                $styles = array_map(fn ($file) => '/build/'.$file, $entry['css'] ?? []);

                return new Response(view('research.labeling', compact('token', 'scripts', 'styles'))->render());
            }
            if (! hash_equals($token, (string) $request->header('X-Research-Token')) || strlen($token) < 32) {
                return new Response('Invalid local request token.', 403);
            }
            $origin = $request->header('Origin');
            if ($origin !== null && $origin !== $request->getSchemeAndHttpHost()) {
                return new Response('Cross-origin requests are forbidden.', 403);
            }
            if ($request->isMethod('GET') && $path === '/api/study') {
                return $this->json($this->workspace->view($dataset));
            }
            if ($request->isMethod('POST') && $path === '/api/save') {
                if (strlen($request->getContent()) > 100000) {
                    return new Response('Request too large.', 413);
                }
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                if (! is_int($data['revision'] ?? null) || ! is_array($data['decision'] ?? null) || ! is_bool($data['attested'] ?? null) || ! is_string($data['pair_id'] ?? null)) {
                    throw new InvalidArgumentException('Malformed save request.');
                }

                return $this->json($this->workspace->save($dataset, $data['pair_id'], $data['decision'], $data['revision'], $data['attested']));
            }
            if ($request->isMethod('POST') && $path === '/api/export') {
                return new Response($this->workspace->export($dataset), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="labels.csv"']);
            }

            return new Response('Not found', 404);
        } catch (InvalidArgumentException|\JsonException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], $exception->getCode() === 409 ? 409 : 403);
        }
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $status, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
