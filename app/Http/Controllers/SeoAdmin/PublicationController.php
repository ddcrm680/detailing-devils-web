<?php

namespace App\Http\Controllers\SeoAdmin;

use App\Http\Controllers\Controller;
use App\Services\Seo\Admin\SeoPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class PublicationController extends Controller
{
    public function publish(Request $request, int $revision, SeoPublisher $publisher)
    {
        return $this->execute($request, $revision, $publisher, false);
    }

    public function rollback(Request $request, int $revision, SeoPublisher $publisher)
    {
        return $this->execute($request, $revision, $publisher, true);
    }

    private function execute(
        Request $request,
        int $revision,
        SeoPublisher $publisher,
        bool $rollback
    ) {
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
            'acknowledge_warnings' => ['accepted'],
            'approve_policy' => ['nullable', 'boolean'],
            'policy_reason' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
        ]);

        $row = DB::table('seo_revisions')
            ->where('id', $revision)
            ->first();
        abort_unless($row, 404);

        try {
            $version = $publisher->publish(
                $revision,
                (int) $input['expected_version'],
                true,
                $request->boolean('approve_policy'),
                Auth::guard('web')->user(),
                $rollback,
                $input['policy_reason'] ?? null
            );
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error(
                'SEO publication failed; review store/artifact health.',
                ['exception' => $exception::class]
            );

            return response()->view('admin.seo.failure', [], 503);
        }

        return redirect()
            ->route('admin.seo.edit', $row->page_id)
            ->with(
                'status',
                'Publication '.$version.' is active. No application deployment was needed.'
            );
    }
}
